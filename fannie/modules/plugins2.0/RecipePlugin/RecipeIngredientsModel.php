<?php

/*******************************************************************************

    Copyright 2017 Whole Foods Co-op

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
  @class RecipeIngredientsModel
*/
class RecipeIngredientsModel extends BasicModel
{

    protected $name = "RecipeIngredients";

    protected $columns = array(
    'recipeIngredientID' => array('type'=>'INT', 'increment'=>true, 'primary_key'=>true),
    'recipeID' => array('type'=>'INT', 'index'=>true),
    'amount' => array('type'=>'VARCHAR(10)'),
    'unit' => array('type'=>'VARCHAR(10)'),
    'name' => array('type'=>'VARCHAR(50)'),
    'notes' => array('type'=>'VARCHAR(255)'),
    'position' => array('type'=>'SMALLINT'),
    );

}

