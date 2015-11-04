<?php
/*******************************************************************************

    Copyright 2015 Whole Foods Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

class InventoryTask extends FannieTask
{

    public $name = 'Inventory (Approximate)';

    public $description = 'Tries to calculate how much inventory is present
    based on recent sales & orders.';

    public $default_schedule = array(
        'min' => 45,
        'hour' => 2,
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
    );

    public function run()
    {
        $dbc = FannieDB::get($this->config->get('OP_DB'));
        $dbc->query('TRUNCATE TABLE InventoryCache');

        $insP = $dbc->prepare('
            INSERT INTO InventoryCache
                (upc, storeID, cacheStart, cacheEnd, baseCount, ordered, sold, shrunk)
            VALUES
                (?, ?, ?, ?, ?, ?, ?)');

        $orderP = $dbc->prepare('
            SELECT SUM(caseSize * receivedQty) AS qty
            FROM PurchaseOrderItems
            WHERE internalUPC=?
                AND receivedDate >= ?');

        $countR = $dbc->query('
            SELECT upc,
                storeID,
                count,
                countDate,
                par
            FROM InventoryCounts
            WHERE mostRecent=1
            ORDER BY countDate DESC');
        $last = array(false, false);
        while ($row = $dbc->fetchRow($countR)) {
            if ($last[0] == $row['upc'] && $last[1] == $row['storeID']) {
                continue;
            }
            $last = array($row['upc'], $row['storeID']);

            $dlog = DTransactions::selectDLog($row['countDate'], date('Y-m-d', strtotime('yesterday')));
            $salesP = $dbc->prepare('
                SELECT d.upc,
                    d.store_id,
                    ' . DTrans::sumQuantity('d') . ' AS qty
                FROM ' . $sales . ' AS d
                    ' . DTrans::joinProducts('d', 'p', 'INNER') . '
                WHERE p.default_vendor_id > 0
                    AND d.trans_status <> \'R\'
                    AND d.upc=?
                    AND d.store_id=?
                GROUP BY d.upc,
                    d.store_id
                HAVING qty > 0');
            $sales = $dbc->getRow($salesP, $last);
            $sales = $sales ? $sales['qty'] : 0;

            $orders = $dbc->getRow($orderP, array($row['upc']));
            $orders ? $orders['qty'] : 0;

            $dtrans = DTransactions::selectDTrans($row['countDate'], date('Y-m-d', strtotime('yesterday')));
            $shrinkP = $dbc->prepare('
                SELECT d.upc,
                    d.store_id,
                    ' . DTrans::sumQuantity('d') . ' AS qty
                FROM ' . $dtrans . ' AS d
                WHERE d.trans_status = \'Z\'
                    AND ' . DTrans::isNotTesting('d') . '
                    AND d.upc=?
                    AND d.store_id=?
                GROUP BY d.upc,
                    d.store_id');
            $shrink = $dbc->getRow($shrinkP, $last);
            $shrink = $shrink ? $shrink['qty'] : 0;

            $args = array(
                $row['upc'],
                $row['storeID'],
                $row['countDate'],
                date('Y-m-d', strtotime('yesterday')),
                $row['count'],
                $orders,
                $sales,
                $shrink,
            );
        }

        $dbc->query('
            UPDATE InventoryCache
            SET onHand = baseCount + ordered - sold - shrunk
        ');
    }
}

