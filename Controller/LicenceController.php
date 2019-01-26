<?php
/*************************************************************************************/
/*      This file is part of the module AdminOrderCreation                           */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AdminOrderCreation\Controller;

use AdminOrderCreation\AdminOrderCreation;
use Thelia\Controller\Admin\AdminController;
use Thelia\Core\HttpFoundation\Request;

class LicenceController extends AdminController
{
    public function licenceAction(Request $request)
    {
        $acceptedLicence = false;
        if ($request->get('accept', false)) {
            AdminOrderCreation::acceptLicence();
            $acceptedLicence = true;
        }

        return $this->render('admin-order-creation/licence', [
            'acceptedLicence' => $acceptedLicence,
            'moduleVersion' => AdminOrderCreation::getVersion()
        ]);
    }
}
