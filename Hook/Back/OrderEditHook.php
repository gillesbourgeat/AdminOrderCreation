<?php
/*************************************************************************************/
/*      This file is part of the module AdminOrderCreation                           */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AdminOrderCreation\Hook\Back;

use Thelia\Core\Event\Hook\HookRenderEvent;

class OrderEditHook extends OrderHook
{
    public function onOrderAddButtonJs(HookRenderEvent $event)
    {
        $event->add($this->render(
            'admin-order-creation/hook/orders.edit.js.html',
            $event->getArguments()
        ));
    }
}
