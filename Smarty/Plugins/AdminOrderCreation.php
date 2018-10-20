<?php

namespace AdminOrderCreation\Smarty\Plugins;

use AdminOrderCreation\Util\Calc;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class AdminOrderCreation extends AbstractSmartyPlugin
{
    /**
     * @return array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor("function", "admin_order_creation_calc_reduction", $this, "adminOrderCreationCalcReduction"),
        );
    }

    public function adminOrderCreationCalcReduction($param)
    {
        return Calc::reduction(
            $param['reduction'],
            $param['reduction_type'],
            $param['price'],
            $param['quantity']
        );
    }
}
