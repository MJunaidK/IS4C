<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of CORE-POS.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

/**
  @class VendorItemsModel
*/
class VendorItemsModel extends BasicModel 
{

    protected $name = "vendorItems";
    protected $preferred_db = 'op';

    protected $columns = array(
    'vendorItemID' => array('type'=>'INT', 'index'=>true, 'increment'=>true),
    'upc' => array('type'=>'VARCHAR(13)','index'=>true),
    'sku' => array('type'=>'VARCHAR(13)','index'=>true,'primary_key'=>true),
    'brand' => array('type'=>'VARCHAR(50)'),
    'description' => array('type'=>'VARCHAR(50)'),
    'size' => array('type'=>'VARCHAR(25)'),
    'units' => array('type'=>'DOUBLE', 'default'=>1),
    'cost' => array('type'=>'MONEY'),
    'saleCost' => array('type'=>'MONEY', 'default'=>0),
    'vendorDept' => array('type'=>'INT', 'default'=>0),
    'vendorID' => array('type'=>'INT','index'=>true,'primary_key'=>true),
    'srp' => array('type'=>'MONEY'),
    'modified' => array('type'=>'datetime', 'ignore_updates'=>true),
    );

    public function doc()
    {
        return '
Table: vendorItems

Columns:
    upc varchar
    sku varchar
    brand varchar
    description varchar
    size varchar
    units int
    cost dbms currency
    saleCost dbms currency
    vendorDept int
    vendorID int

Depends on:
    vendors (table)
    vendorDepartments (table)

Use:
This table has items from vendors. Cost
and vendor department margin are used to 
calculate SRPs, but the other fields are useful
for making shelf tags.

Size relates to an indivdual product.
Units relates to a case. So a case of beer has 24
units, each with a size of 12 oz.

Cost represents the unit cost. Cost times units 
should then equal the case cost. Sale Cost is
for storing temporary special prices.

upc corresponds to products.upc. Multiple vendorItems
records may map to one products record if an item
is available from more than one vendor or under 
several SKUs from the one vendor. sku should 
uniquely identify an item for the purpose of ordering
it from the vendor. If the vendor does not have SKUs
you have to assign some. The field is wide enough
to hold a UPC; putting your UPC or the vendor\'s UPC
in the SKU field may be a simple solution to assigning
SKUs.
        ';
    }

    /**
      Helper: create a vendorItems record for an existing
      product if one does not exist
    */
    public function createIfMissing($upc, $vendorID)
    {
        // look for entry directly by UPC or via SKU mapping
        $findP = $this->connection->prepare('
            SELECT v.upc
            FROM vendorItems AS v
                LEFT JOIN vendorSKUtoPLU AS m ON v.vendorID=m.vendorID AND v.sku=m.sku
            WHERE v.vendorID=?
                AND (v.upc=? OR m.upc=?)');
        $findR = $this->connection->execute($findP, array($vendorID, $upc, $upc));
        if ($this->connection->num_rows($findR) == 0) {
            // create item from product
            $prod = new ProductsModel($this->connection);
            $prod->upc($upc);
            $prod->load();
            $vend = new VendorItemsModel($this->connection);
            $vend->vendorID($vendorID);
            $vend->upc($upc);
            $vend->sku($upc);
            $vend->brand($prod->brand());
            $vend->description($prod->description());
            $vend->cost($prod->cost());
            $vend->saleCost(0);
            $vend->vendorDept(0);
            $vend->units(1);
            $vend->size($prod->size() . $prod->unitofmeasure());
            $vend->save();
        }
    }

    /**
      Helper: update vendor costs when updating a product cost
      if the product has a defined vendor
    */
    public function updateCostByUPC($upc, $cost, $vendorID)
    {
        $updateP = $this->connection->prepare('
            UPDATE vendorItems
            SET cost=?,
                modified=' . $this->connection->now() . '
            WHERE vendorID=?
                AND sku=?'); 
        $skuModel = new VendorSKUtoPLUModel($this->connection);
        $skuModel->vendorID($vendorID);
        $skuModel->upc($upc);
        foreach ($skuModel->find() as $obj) {
            $this->connection->execute($updateP, array($cost, $vendorID, $obj->sku()));
        }

        $vModel = new VendorItemsModel($this->connection);
        $vModel->vendorID($vendorID);
        $vModel->upc($upc);
        foreach ($vModel->find() as $obj) {
            $this->connection->execute($updateP, array($cost, $vendorID, $obj->sku()));
        }
    }

    public function save()
    {
        if ($this->record_changed) {
            $this->modified(date('Y-m-d H:i:s'));
        }

        return parent::save();
    }

    /* START ACCESSOR FUNCTIONS */

    public function vendorItemID()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["vendorItemID"])) {
                return $this->instance["vendorItemID"];
            } else if (isset($this->columns["vendorItemID"]["default"])) {
                return $this->columns["vendorItemID"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'vendorItemID',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["vendorItemID"]) || $this->instance["vendorItemID"] != func_get_args(0)) {
                if (!isset($this->columns["vendorItemID"]["ignore_updates"]) || $this->columns["vendorItemID"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["vendorItemID"] = func_get_arg(0);
        }
        return $this;
    }

    public function upc()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["upc"])) {
                return $this->instance["upc"];
            } else if (isset($this->columns["upc"]["default"])) {
                return $this->columns["upc"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'upc',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["upc"]) || $this->instance["upc"] != func_get_args(0)) {
                if (!isset($this->columns["upc"]["ignore_updates"]) || $this->columns["upc"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["upc"] = func_get_arg(0);
        }
        return $this;
    }

    public function sku()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["sku"])) {
                return $this->instance["sku"];
            } else if (isset($this->columns["sku"]["default"])) {
                return $this->columns["sku"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'sku',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["sku"]) || $this->instance["sku"] != func_get_args(0)) {
                if (!isset($this->columns["sku"]["ignore_updates"]) || $this->columns["sku"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["sku"] = func_get_arg(0);
        }
        return $this;
    }

    public function brand()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["brand"])) {
                return $this->instance["brand"];
            } else if (isset($this->columns["brand"]["default"])) {
                return $this->columns["brand"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'brand',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["brand"]) || $this->instance["brand"] != func_get_args(0)) {
                if (!isset($this->columns["brand"]["ignore_updates"]) || $this->columns["brand"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["brand"] = func_get_arg(0);
        }
        return $this;
    }

    public function description()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["description"])) {
                return $this->instance["description"];
            } else if (isset($this->columns["description"]["default"])) {
                return $this->columns["description"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'description',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["description"]) || $this->instance["description"] != func_get_args(0)) {
                if (!isset($this->columns["description"]["ignore_updates"]) || $this->columns["description"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["description"] = func_get_arg(0);
        }
        return $this;
    }

    public function size()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["size"])) {
                return $this->instance["size"];
            } else if (isset($this->columns["size"]["default"])) {
                return $this->columns["size"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'size',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["size"]) || $this->instance["size"] != func_get_args(0)) {
                if (!isset($this->columns["size"]["ignore_updates"]) || $this->columns["size"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["size"] = func_get_arg(0);
        }
        return $this;
    }

    public function units()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["units"])) {
                return $this->instance["units"];
            } else if (isset($this->columns["units"]["default"])) {
                return $this->columns["units"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'units',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["units"]) || $this->instance["units"] != func_get_args(0)) {
                if (!isset($this->columns["units"]["ignore_updates"]) || $this->columns["units"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["units"] = func_get_arg(0);
        }
        return $this;
    }

    public function cost()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["cost"])) {
                return $this->instance["cost"];
            } else if (isset($this->columns["cost"]["default"])) {
                return $this->columns["cost"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'cost',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["cost"]) || $this->instance["cost"] != func_get_args(0)) {
                if (!isset($this->columns["cost"]["ignore_updates"]) || $this->columns["cost"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["cost"] = func_get_arg(0);
        }
        return $this;
    }

    public function saleCost()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["saleCost"])) {
                return $this->instance["saleCost"];
            } else if (isset($this->columns["saleCost"]["default"])) {
                return $this->columns["saleCost"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'saleCost',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["saleCost"]) || $this->instance["saleCost"] != func_get_args(0)) {
                if (!isset($this->columns["saleCost"]["ignore_updates"]) || $this->columns["saleCost"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["saleCost"] = func_get_arg(0);
        }
        return $this;
    }

    public function vendorDept()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["vendorDept"])) {
                return $this->instance["vendorDept"];
            } else if (isset($this->columns["vendorDept"]["default"])) {
                return $this->columns["vendorDept"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'vendorDept',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["vendorDept"]) || $this->instance["vendorDept"] != func_get_args(0)) {
                if (!isset($this->columns["vendorDept"]["ignore_updates"]) || $this->columns["vendorDept"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["vendorDept"] = func_get_arg(0);
        }
        return $this;
    }

    public function vendorID()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["vendorID"])) {
                return $this->instance["vendorID"];
            } else if (isset($this->columns["vendorID"]["default"])) {
                return $this->columns["vendorID"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'vendorID',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["vendorID"]) || $this->instance["vendorID"] != func_get_args(0)) {
                if (!isset($this->columns["vendorID"]["ignore_updates"]) || $this->columns["vendorID"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["vendorID"] = func_get_arg(0);
        }
        return $this;
    }

    public function srp()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["srp"])) {
                return $this->instance["srp"];
            } else if (isset($this->columns["srp"]["default"])) {
                return $this->columns["srp"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'srp',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["srp"]) || $this->instance["srp"] != func_get_args(0)) {
                if (!isset($this->columns["srp"]["ignore_updates"]) || $this->columns["srp"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["srp"] = func_get_arg(0);
        }
        return $this;
    }

    public function modified()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["modified"])) {
                return $this->instance["modified"];
            } else if (isset($this->columns["modified"]["default"])) {
                return $this->columns["modified"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'modified',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["modified"]) || $this->instance["modified"] != func_get_args(0)) {
                if (!isset($this->columns["modified"]["ignore_updates"]) || $this->columns["modified"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["modified"] = func_get_arg(0);
        }
        return $this;
    }
    /* END ACCESSOR FUNCTIONS */
}

