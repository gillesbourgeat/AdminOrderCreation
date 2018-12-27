<?php
/*************************************************************************************/
/*      This file is part of the module AdminOrderCreation                           */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AdminOrderCreation\Model;

class Order extends \Thelia\Model\Order
{
    public function getTotalAmountWithTax($withDiscount = true)
    {
        $total = 0;

        foreach ($this->getOrderProducts() as $orderProduct) {
            if ($orderProduct->getWasInPromo()) {
                $total += $orderProduct->getPromoPrice() * $orderProduct->getQuantity();
            } else {
                $total += $orderProduct->getPrice() * $orderProduct->getQuantity();
            }

            foreach ($orderProduct->getOrderProductTaxes() as $orderProductTax) {
                if ($orderProduct->getWasInPromo()) {
                    $total += $orderProductTax->getPromoAmount() * $orderProduct->getQuantity();
                } else {
                    $total += $orderProductTax->getAmount() * $orderProduct->getQuantity();
                }
            }
        }

        $total += $this->getPostage();


        if ($withDiscount) {
            $total -= $this->getDiscount();
        }

        return $total;
    }

    public function getTotalAmountWithoutTax($withDiscount = true)
    {
        $total = 0;

        foreach ($this->getOrderProducts() as $orderProduct) {
            if ($orderProduct->getWasInPromo()) {
                $total += $orderProduct->getPromoPrice() * $orderProduct->getQuantity();
            } else {
                $total += $orderProduct->getPrice() * $orderProduct->getQuantity();
            }
        }

        $total += $this->getPostage() - $this->getPostageTax();

        if ($withDiscount) {
            $total -= $this->getDiscount();
        }

        return $total;
    }
}