<?php
namespace AdminOrderCreation\Hook\Back;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class OrderHook extends BaseHook
{
    public function onOrdersTableHeader(HookRenderEvent $event)
    {
        $event->add($this->render(
            'admin-order-creation/hook/orders.table-header.html',
            $event->getArguments()
        ));
    }

    public function onOrderJs(HookRenderEvent $event)
    {
        $event->add($this->render(
            'admin-order-creation/hook/orders.js.html',
            $event->getArguments()
        ));
    }
}
