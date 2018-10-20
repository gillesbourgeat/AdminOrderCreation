<?php

namespace AdminOrderCreation\Util;

class Calc
{
    public static function reduction($value, $type, $price, $quantity = 1)
    {
        $type = (int) $type;
        $value = (float) $value;
        $quantity = (float) $quantity;

        if ($type === 1) {
            if ($value < 0 || $value > 100) {
                throw new \Exception('Invalid arg reduction');
            }

            return (($price / 100) * (100 - $value));
        } elseif ($type === 2) {
            if ($value < 0 || $value > $price * $quantity) {
                throw new \Exception('Invalid arg reduction');
            }

            return ($price * $quantity - $value) / $quantity;
        } elseif ($type === 3) {
            if ($value < 0 || $value > $price) {
                throw new \Exception('Invalid arg reduction');
            }

            return ($price - $value);
        }
    }
}
