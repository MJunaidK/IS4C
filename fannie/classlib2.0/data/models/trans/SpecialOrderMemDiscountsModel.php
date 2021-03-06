<?php
/*******************************************************************************

    Copyright 2015 Whole Foods Co-op

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
  @class SpecialOrderMemDiscountsModel
*/
class SpecialOrderMemDiscountsModel extends BasicModel
{
    protected $name = "SpecialOrderMemDiscounts";
    protected $preferred_db = 'trans';

    protected $columns = array(
    'specialOrderMemDiscountID' => array('type'=>'INT', 'increment'=>true, 'index'=>true),
    'memType' => array('type'=>'SMALLINT', 'primary_key'=>true),
    'type' => array('type'=>'VARCHAR(10)', 'default'=>"'markdown'"),
    'amount' => array('type'=>'DOUBLE', 'default'=>0),
    );

    public function doc()
    {
        return '
Use:
Defines special order discounts based on member type. Amount is a percentage
and type should be markdown (from retail) or markup (from cost).
            ';
    }
}

