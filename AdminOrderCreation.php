<?php
/*************************************************************************************/
/*      This file is part of the module AdminOrderCreation                           */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AdminOrderCreation;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

class AdminOrderCreation extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'adminordercreation';

    const CONFIG_KEY_DEFAULT_NEW_CREDIT_NOTE_STATUS_ID = 'default-new-credit-note-status-id';
    const CONFIG_KEY_DEFAULT_NEW_CREDIT_NOTE_TYPE_ID = 'default-new-credit-note-type-id';
    const CONFIG_KEY_PAYED_ORDER_MINIMUM_STATUS_ID = 'payed-order-minimum-status-id';
    const CONFIG_KEY_INVOICE_REF_TYPE = 'invoice-ref-type'; // 0 for default thelia, 1 for separate
    const CONFIG_KEY_INVOICE_REF_INCREMENT = 'invoice-ref-increment';

    const CONFIG_DEFAULT_VALUE_DEFAULT_NEW_CREDIT_NOTE_STATUS_ID = 4;
    const CONFIG_DEFAULT_VALUE_DEFAULT_NEW_CREDIT_NOTE_TYPE_ID = 7;
    const CONFIG_DEFAULT_VALUE_PAYED_ORDER_MINIMUM_STATUS_ID = 2;
    const CONFIG_DEFAULT_VALUE_INVOICE_REF_TYPE = 0;

    public function update($currentVersion, $newVersion, ConnectionInterface $con = null)
    {
        if (null === self::getConfigValue(self::CONFIG_KEY_DEFAULT_NEW_CREDIT_NOTE_STATUS_ID)) {
            self::setConfigValue(
                self::CONFIG_KEY_DEFAULT_NEW_CREDIT_NOTE_STATUS_ID,
                self::CONFIG_DEFAULT_VALUE_DEFAULT_NEW_CREDIT_NOTE_STATUS_ID
            );
        }

        if (null === self::getConfigValue(self::CONFIG_KEY_DEFAULT_NEW_CREDIT_NOTE_TYPE_ID)) {
            self::setConfigValue(
                self::CONFIG_KEY_DEFAULT_NEW_CREDIT_NOTE_TYPE_ID,
                self::CONFIG_DEFAULT_VALUE_DEFAULT_NEW_CREDIT_NOTE_TYPE_ID
            );
        }

        if (null === self::getConfigValue(self::CONFIG_KEY_PAYED_ORDER_MINIMUM_STATUS_ID)) {
            self::setConfigValue(
                self::CONFIG_KEY_PAYED_ORDER_MINIMUM_STATUS_ID,
                self::CONFIG_DEFAULT_VALUE_PAYED_ORDER_MINIMUM_STATUS_ID
            );
        }

        if (null === self::getConfigValue(self::CONFIG_KEY_INVOICE_REF_TYPE)) {
            self::setConfigValue(
                self::CONFIG_KEY_INVOICE_REF_TYPE,
                self::CONFIG_DEFAULT_VALUE_INVOICE_REF_TYPE
            );
        }

        if (null === self::getConfigValue(self::CONFIG_KEY_INVOICE_REF_INCREMENT)) {
            self::setConfigValue(
                self::CONFIG_KEY_INVOICE_REF_INCREMENT,
                1
            );
        }
    }

    public static function checkLicence()
    {
        return static::getConfigValue('licenceVersion', '') === self::getVersion();
    }

    public static function acceptLicence()
    {
        static::setConfigValue('licenceVersion', self::getVersion());
    }

    public static function getVersion()
    {
        return ModuleQuery::create()->findOneByCode(self::getModuleCode())->getVersion();
    }
}
