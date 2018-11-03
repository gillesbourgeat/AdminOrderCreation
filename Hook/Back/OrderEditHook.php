<?php
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
