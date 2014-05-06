<?php

class Own_Drm_Helper_Data extends Mage_Core_Helper_Abstract {
    /**
     * Log file
     */

    const LOGFILE = "Own_Drm_Data.log";
    const ERROR_INVALID_USER = 'Invalid user or not enough rights to submit orders';
    const ERROR_INVALID_XML = 'Invalid XML';
    const ERROR_NONEXISTANT_CUSTOMER_NODE = '"Customer" Node not found';
    const ERROR_NONEXISTANT_ORDER_CUSTOMER = 'Customer Password is wrong';
    const ERROR_UNDEFINED = 'Please check with support';
    const ERROR_NONEXISTANT_PASSWORD_NODE = '"Password" Node not found';
    const ERROR_NONEXISTANT_NEWEMAIL_NODE = '"New Email" Node not found';
    const ERROR_NONEXISTANT_CUSTOMER = '"Customer" not found';
    const ERROR_EMAIL_EXISTS = 'Email already exists';
    const SUCCESSFUL = 'Order Placed Successful';

    /**
     * @var array $errors
     */
    public static $errors = array(
        0 => self::SUCCESSFUL,
        '-1' => self::ERROR_INVALID_USER,
        '-2' => self::ERROR_INVALID_XML,
        '-3' => self::ERROR_NONEXISTANT_CUSTOMER_NODE,
        '-4' => self::ERROR_NONEXISTANT_ORDER_CUSTOMER,
        '-5' => self::ERROR_UNDEFINED
    );
    public static $errorsemail = array(
        0 => self::SUCCESSFUL,
        '-1' => self::ERROR_INVALID_USER,
        '-2' => self::ERROR_INVALID_XML,
        '-3' => self::ERROR_NONEXISTANT_CUSTOMER_NODE,
        '-4' => self::ERROR_NONEXISTANT_CUSTOMER,
        '-5' => self::ERROR_NONEXISTANT_PASSWORD_NODE,
        '-7' => self::ERROR_NONEXISTANT_NEWEMAIL_NODE,
        '-8' => self::ERROR_EMAIL_EXISTS,
    );

    /**     * **************************************************************************** * */
    /**     * ***************************** SOAP-functions ******************************* * */
    /**     * **************************************************************************** * */

    /**
     * Change password.
     *
     * @version 0.1
     *
     * @param Mage_Customer_Model_Customer $Customer
     * @param string $currentPassword
     * @param string $newPassword
     *
     * @return boolean
     */
    public static function changePassword(Mage_Customer_Model_Customer $Customer, $currentPassword, $newPassword, $bHashvaluesGiven = false) {
        self::_log('changePassword');

        $customerId = $Customer->getId();
        $wsdl = Mage::getStoreConfig('own_drm/drm/drm_wsdl');
        $SoapClient = new SoapClient($wsdl, array(
            'soap_version' => SOAP_1_3,
            'trace' => true,
            'classmap' => array(
                'CreateCustomerPasswordResponse' => 'Own_Drm_Model_Drm_Soap_Response_ChangeCustomerPassword'
            ),
            'cache_wsdl' => WSDL_CACHE_NONE
                )
        );

        $soapRequest = array();
        $soapRequest['EMail'] = Mage::getStoreConfig('own_drm/drm/drm_email');
        $soapRequest['Password'] = Mage::getStoreConfig('own_drm/drm/drm_password');

        $Doc = new DOMDocument('1.0', 'UTF-8');
        $Doc->formatOutput = true;
        $_Customer = $Doc->createElement('Customer');
        $Doc->appendChild($_Customer);

        $_Email = $Doc->createElement('EMail', $Customer->getEmail());
        $_Customer->appendChild($_Email);

        if ($bHashvaluesGiven) {
            $Password = $Doc->createElement('PasswordHash', $currentPassword);
            $_Customer->appendChild($Password);

            $NewPassword = $Doc->createElement('NewPasswordHash', $newPassword);
            $_Customer->appendChild($NewPassword);
        } else {
            $Password = $Doc->createElement('PasswordHash', $Customer->hashPassword($currentPassword));
            $_Customer->appendChild($Password);

            $NewPassword = $Doc->createElement('NewPasswordHash', $Customer->hashPassword($newPassword));
            $_Customer->appendChild($NewPassword);
        }

        $soapRequest['XML'] = $Doc->saveXML();
        try {
            $Result = $SoapClient->__call('ChangeCustomerPassword', array('parameters' => $soapRequest));
            $Result->ChangeCustomerPasswordResult;

            self::_log('Request:');
            self::_log(html_entity_decode($SoapClient->__getLastRequest()));
            self::_log('Response:');
            self::_log($SoapClient->__getLastResponse());

            if ($Result->ErrorCode < 0) {
                return $Result->ErrorCode;
            } else {
                return true;
            }
        } catch (SoapFault $Exception) {
            self::_log($Exception->getMessage());
            return false;
        }
    }

    public static function changeEmail(Mage_Customer_Model_Customer $Customer, $strNewEmail) {
        self::_log('changeEmail');

        $customerId = $Customer->getId();

        $wsdl = Mage::getStoreConfig('own_drm/drm/drm_wsdl');
        $SoapClient = new SoapClient($wsdl, array(
            'soap_version' => SOAP_1_3,
            'trace' => true,
            'classmap' => array(
                'ChangeCustomerEMailResponse' => 'Own_Drm_Model_Drm_Soap_Response_ChangeCustomerEMail'
            ),
            'cache_wsdl' => WSDL_CACHE_NONE
                )
        );

        $soapRequest = array();
        $soapRequest['EMail'] = Mage::getStoreConfig('own_drm/drm/drm_email');
        $soapRequest['Password'] = Mage::getStoreConfig('own_drm/drm/drm_password');

        $Doc = new DOMDocument('1.0', 'UTF-8');
        $Doc->formatOutput = true;
        $_Customer = $Doc->createElement('Customer');
        $Doc->appendChild($_Customer);

        $_Email = $Doc->createElement('EMail', $Customer->getEmail());
        $_Customer->appendChild($_Email);

        $_NewEmail = $Doc->createElement('NewEMail', $strNewEmail);
        $_Customer->appendChild($_NewEmail);

        $Password = $Doc->createElement('PasswordHash', $Customer->getData("password_hash"));
        $_Customer->appendChild($Password);

        $soapRequest['XML'] = $Doc->saveXML();
        try {
            $Result = $SoapClient->__call('ChangeCustomerEMail', array('parameters' => $soapRequest));
            $Result->ChangeCustomerEMailResult;

            self::_log('Request:');
            self::_log($SoapClient->__getLastRequest());
            self::_log('Response:');
            self::_log($SoapClient->__getLastResponse());

            if ($Result->ErrorCode < 0) {
                return $Result->ErrorCode;
            } else {
                return true;
            }
        } catch (SoapFault $Exception) {
            self::_log($Exception->getMessage());
            return false;
        }
    }

    public static function createShopOrder($Order) {
        self::_log('createShopOrder');

        $iIdCustomer = $Order->getCustomerId();
        $Customer = Mage::getModel('customer/customer')->load($iIdCustomer);

        $wsdl = Mage::getStoreConfig('own_drm/drm/drm_wsdl');
        $SoapClient = new SoapClient($wsdl, array('soap_version' => SOAP_1_3,
            'trace' => true,
            'classmap' => array('CreateShopOrderResponse' => 'Own_Drm_Model_Drm_Soap_Response_CreateShopOrder'),
            'cache_wsdl' => WSDL_CACHE_NONE
                )
        );

        $soapRequest = array();
        $soapRequest['EMail'] = Mage::getStoreConfig('own_drm/drm/drm_email');
        $soapRequest['Password'] = Mage::getStoreConfig('own_drm/drm/drm_password');

        $Doc = new DOMDocument('1.0', 'UTF-8');
        $Doc->formatOutput = true;
        $_Order = $Doc->createElement('Order');
        $Doc->appendChild($_Order);

        $TransactionID = $Doc->createElement('TransactionID', $Order->getRealOrderId());
        $_Order->appendChild($TransactionID);

        $ThemeID = $Doc->createElement('ThemeID', Mage::getStoreConfig('own_drm/drm/drm_theme_id'));
        $_Order->appendChild($ThemeID);

        $EMail = $Doc->createElement('EMail', $Customer->getEmail());
        $_Order->appendChild($EMail);

        $Password = $Doc->createElement('PasswordHash', $Customer->getPasswordHash());
        $_Order->appendChild($Password);

        $Items = $Doc->createElement('Items');
        $_Order->appendChild($Items);

        $iSentItems = 0;
        $_Items = $Order->getAllItems();

        foreach ($_Items as $_Item) {
            $productId = $_Item->getData('product_id');
            $Product = Mage::getModel('catalog/product')->load($productId);
           
            $attributeSetModel = Mage::getModel("eav/entity_attribute_set");
            $attributeSetModel->load($Product->getAttributeSetId());
            $attributeSetName = $attributeSetModel->getAttributeSetName();

            self::_log('ProductId: ' . $productId . ', ' .
                    'AttributeSetId: ' . $Product->getAttributeSetId() . ', ' .
                    'AttributeSetName: ' . $attributeSetName);

            /**
             * sent only digital items 
             */
            if ($attributeSetName != 'DrmProduct') {
                self::_log('No DrmProduct (do not add it to XML)');
                continue;
            }
            
            $options = ($_Item->getProductOptions());
            foreach ($options['options'] as $_eachOption) {
                $objModel = Mage::getModel('catalog/product_option_value')->load($_eachOption['option_value']);
                $sku=$objModel->getData();
                $licenseId=$sku['sku'];
            }
            
            
            switch ($licenseId) {
                case '146':$licenseId = 146;
                    $hd = 0;
                    break;
                case '258':$licenseId = 258;
                    $hd = 0;
                    break;
                case '146_hd':$licenseId = 146;
                    $hd = 1;
                    break;
                case '258_hd':$licenseId = 258;
                    $hd = 1;
                    break;
            }
            $Item = $Doc->createElement('Item');
            $Items->appendChild($Item);
            $ProjectID = $Doc->createElement('ProjectID', $Product->getSku());
            $Item->appendChild($ProjectID);

            $LicenseID = $Doc->createElement('LicenseID', $licenseId); /* NOTE - License ID HARD CODED */
            $Item->appendChild($LicenseID);

            $Count = $Doc->createElement('Count', 1); // $_Item->getQtyToShip());
            $Item->appendChild($Count);

            $hdVer = $Doc->createElement('HD', $hd);
            $Item->appendChild($hdVer);

            $iSentItems++;
        }

        if (0 == $iSentItems) {
            /**
             * don't sent data, if the order has no digital items 
             */
            self::_log('do not sent data, if the order has no digital items ');
            return;
        }

        $soapRequest['XML'] = $Doc->saveXML();
        try {
            $Result = $SoapClient->__call('CreateShopOrder', array('parameters' => $soapRequest));

            self::_log('Request:');
            self::_log($SoapClient->__getLastRequest());
            self::_log('Response:');
            self::_log($SoapClient->__getLastResponse());

            self::_logdrm($Order->getId(), $Order->getStore()->getId(), $Customer->getId(), $SoapClient->__getLastRequest(), $SoapClient->__getLastResponse(), $Result->ErrorCode);
        } catch (SoapFault $Exception) {
            self::_log($Exception->getMessage());
        }
    }

    /**     * **************************************************************************** * */
    /**     * *********************** SOAP-functions (as in the API) ********************* * */
    /**     * **************************************************************************** * */

    /**
     * @param int $iTransactionId
     * @param string $strEmail
     * @param string $strPassword
     * @param bool $bPwHash - true means, the password is given as a hash
     * @return 	'OK' = email and password are correct or user email does not exist
     * 			'PASSWORD_WRONG' = email is correct but password is wrong
     * 			'SOAP_EXCEPTION'
     */
    public static function createShopUser($iTransactionId, $strEmail, $strPassword, $bPwHash = true) {
        self::_log('createShopUser');

        $wsdl = Mage::getStoreConfig('own_drm/drm/drm_wsdl');
        $SoapClient = new SoapClient($wsdl, array('soap_version' => SOAP_1_3,
            'trace' => true,
            'classmap' => array('CreateShopOrderResponse' => 'Own_Drm_Model_Drm_Soap_Response_CreateShopOrder'),
            'cache_wsdl' => WSDL_CACHE_NONE
                )
        );

        $soapRequest = array();
        $soapRequest['EMail'] = Mage::getStoreConfig('own_drm/drm/drm_email');
        $soapRequest['Password'] = Mage::getStoreConfig('own_drm/drm/drm_password');

        $Doc = new DOMDocument('1.0', 'UTF-8');
        $Doc->formatOutput = true;
        $_Order = $Doc->createElement('Order');
        $Doc->appendChild($_Order);

        $TransactionID = $Doc->createElement('TransactionID', '999999' . $iTransactionId);
        $_Order->appendChild($TransactionID);

        $ThemeID = $Doc->createElement('ThemeID', Mage::getStoreConfig('own_drm/drm/drm_theme_id'));
        $_Order->appendChild($ThemeID);

        $EMail = $Doc->createElement('EMail', $strEmail);
        $_Order->appendChild($EMail);

        if ($bPwHash) {
            $Password = $Doc->createElement('PasswordHash', $strPassword);
            $_Order->appendChild($Password);
        } else {
            $Password = $Doc->createElement('Password', $strPassword);
            $_Order->appendChild($Password);
        }

        $soapRequest['XML'] = $Doc->saveXML();
        try {
            $Result = $SoapClient->__call('CreateShopOrder', array('parameters' => $soapRequest));

            self::_log('Request:');
            self::_log($SoapClient->__getLastRequest());
            self::_log('Response:');
            self::_log($SoapClient->__getLastResponse());

            if ($Result->CreateShopOrderResult == 1 && $Result->ErrorCode == 0) {
                return 'OK';
            }
            if ($Result->ErrorCode == -4) {
                return 'PASSWORD_WRONG';
            }
        } catch (SoapFault $Exception) {
            self::_log($Exception->getMessage());
            return 'SOAP_EXCEPTION';
        }
    }

    /**
     * @param string $strEmail
     * @param string $strPassword
     * @param bool $bPwHash - true means, the password is given as a hash
     * @return 	'OK' = email and password are correct or user email does not exist 
     * 			'PASSWORD_WRONG' = email is correct but password is wrong 
     * 			'SOAP_EXCEPTION'
     */
    public static function checkUserExists($strEmail, $strPassword, $bPwHash = true) {
        self::_log('checkUserExists');

        $wsdl = Mage::getStoreConfig('own_drm/drm/drm_wsdl');
        $SoapClient = new SoapClient($wsdl, array('soap_version' => SOAP_1_3,
            'trace' => true,
            'classmap' => array('CheckUserExistsResponse' => 'Own_Drm_Model_Drm_Soap_Response_CheckUserExists'),
            'cache_wsdl' => WSDL_CACHE_NONE
                )
        );

        $soapRequest = array();
        $soapRequest['EMail'] = Mage::getStoreConfig('own_drm/drm/drm_email');
        $soapRequest['Password'] = Mage::getStoreConfig('own_drm/drm/drm_password');

        $Doc = new DOMDocument('1.0', 'UTF-8');
        $Doc->formatOutput = true;
        $_Customer = $Doc->createElement('Customer');
        $Doc->appendChild($_Customer);

        $EMail = $Doc->createElement('EMail', $strEmail);
        $_Customer->appendChild($EMail);

        if ($bPwHash) {
            $Password = $Doc->createElement('PasswordHash', $strPassword);
            $_Customer->appendChild($Password);
        } else {
            $Password = $Doc->createElement('Password', $strPassword);
            $_Customer->appendChild($Password);
        }

        $soapRequest['XML'] = $Doc->saveXML();
        try {
            $Result = $SoapClient->__call('CheckUserExists', array('parameters' => $soapRequest));

            self::_log('Request:');
            self::_log($SoapClient->__getLastRequest());
            self::_log('Response:');
            self::_log($SoapClient->__getLastResponse());

            if ($Result->CheckUserExistsResult == 1 && $Result->ErrorCode == 0) {
                return 'OK';
            }
            if ($Result->ErrorCode == -5) {
                return 'PASSWORD_WRONG';
            }
            return $Result->ErrorCode;
        } catch (SoapFault $Exception) {
            self::_log($Exception->getMessage());
            return 'SOAP_EXCEPTION';
        }
    }

    /**
     * 
     * @param string $strEmail
     * @param string $strPwOld
     * @param bool $bPwOldHash
     * @param string $strPwNew
     * @param bool $bPwNewHash
     * @return 	'OK' = email and password are correct or user email does not exist
     * 			'PASSWORD_WRONG' = email is correct but password is wrong
     * 			'SOAP_EXCEPTION'
     */
    public static function changeCustomerPassword($strEmail, $strPwOld, $bPwOldHash, $strPwNew, $bPwNewHash) {

        self::_log('changeCustomerPassword');

        $wsdl = Mage::getStoreConfig('own_drm/drm/drm_wsdl');
        $SoapClient = new SoapClient($wsdl, array('soap_version' => SOAP_1_3,
            'trace' => true,
            'classmap' => array('ChangeCustomerPasswordResponse' => 'Own_Drm_Model_Drm_Soap_Response_ChangeCustomerPassword'),
            'cache_wsdl' => WSDL_CACHE_NONE
                )
        );

        $soapRequest = array();
        $soapRequest['EMail'] = Mage::getStoreConfig('own_drm/drm/drm_email');
        $soapRequest['Password'] = Mage::getStoreConfig('own_drm/drm/drm_password');

        $Doc = new DOMDocument('1.0', 'UTF-8');
        $Doc->formatOutput = true;
        $_Customer = $Doc->createElement('Customer');
        $Doc->appendChild($_Customer);

        $_Email = $Doc->createElement('EMail', $strEmail);
        $_Customer->appendChild($_Email);

        if ($bPwOldHash == true) {
            $Password = $Doc->createElement('PasswordHash', $strPwOld);
        } else {
            $Password = $Doc->createElement('Password', $strPwOld);
        }
        $_Customer->appendChild($Password);

        if ($bPwNewHash == true) {
            $NewPassword = $Doc->createElement('NewPasswordHash', $strPwNew);
        } else {
            $NewPassword = $Doc->createElement('NewPassword', $strPwNew);
        }
        $_Customer->appendChild($NewPassword);

        $soapRequest['XML'] = $Doc->saveXML();
        try {
            $Result = $SoapClient->__call('ChangeCustomerPassword', array('parameters' => $soapRequest));

            self::_log('Request:');
            self::_log($SoapClient->__getLastRequest());
            self::_log('Response:');
            self::_log($SoapClient->__getLastResponse());

            if ($Result->ChangeCustomerPasswordResult == 1 && $Result->ErrorCode == 0) {
                return 'OK';
            }
            if ($Result->ErrorCode == -4) {
                return 'EMAIL_NOT_FOUND';
            }
        } catch (SoapFault $Exception) {
            self::_log($Exception->getMessage());
            return 'SOAP_EXCEPTION';
        }
    }

    /**
     */
    public static function getProjects() {
        self::_log('getProjects');

        $wsdl = Mage::getStoreConfig('own_drm/drm/drm_wsdl');
        $SoapClient = new SoapClient($wsdl, array('soap_version' => SOAP_1_3,
            'trace' => true,
            'classmap' => array('CheckUserExistsResponse' => 'Own_Drm_Model_Drm_Soap_Response_GetProjects'),
            'cache_wsdl' => WSDL_CACHE_NONE
                )
        );

        $soapRequest = array();
        $soapRequest['EMail'] = Mage::getStoreConfig('own_drm/drm/drm_email');
        $soapRequest['Password'] = Mage::getStoreConfig('own_drm/drm/drm_password');
        $soapRequest['ThemeID'] = Mage::getStoreConfig('own_drm/drm/drm_theme_id');

        $Doc = new DOMDocument('1.0', 'UTF-8');
        $Doc->formatOutput = true;
        $soapRequest['XML'] = $Doc->saveXML();
        try {
            $Result = $SoapClient->__call('GetProjects', array('parameters' => $soapRequest));

            self::_log('Request:');
            self::_log($SoapClient->__getLastRequest());
            self::_log('Response:');
            self::_log($SoapClient->__getLastResponse());

            if ($Result->GetProjectsResult == true && $Result->ErrorCode == 0) {
             
                if (is_array($Result->Projects->stProject)) {
                    return $Result->Projects->stProject;
                } else {
                    $arrReturn = array();
                    $arrReturn[] = $Result->Projects->stProject;
                    return $arrReturn;
                }


                foreach ($Result->Licenses->stLicense as $license) {
                    echo "\n<br />" . '***';
                    echo "\n<br />" . $license->ID;
                    echo "\n<br />" . $license->Name;
                    echo "\n<br />" . $license->LicType;
                }
            }
        } catch (SoapFault $Exception) {
            self::_log($Exception->getMessage());
            return 'SOAP_EXCEPTION';
        }
    }

    /**
     */
    public static function getLicenses() {
        self::_log('getLicenses');

        $wsdl = Mage::getStoreConfig('own_drm/drm/drm_wsdl');
        $SoapClient = new SoapClient($wsdl, array('soap_version' => SOAP_1_3,
            'trace' => true,
            'classmap' => array('CheckUserExistsResponse' => 'Own_Drm_Model_Drm_Soap_Response_GetLicenses'),
            'cache_wsdl' => WSDL_CACHE_NONE
                )
        );
        $soapRequest = array();
        $soapRequest['EMail'] = Mage::getStoreConfig('own_drm/drm/drm_email');
        $soapRequest['Password'] = Mage::getStoreConfig('own_drm/drm/drm_password');
        $soapRequest['ThemeID'] = Mage::getStoreConfig('own_drm/drm/drm_theme_id');

        $Doc = new DOMDocument('1.0', 'UTF-8');
        $Doc->formatOutput = true;
        $soapRequest['XML'] = $Doc->saveXML();
        try {
            $Result = $SoapClient->__call('GetLicenses', array('parameters' => $soapRequest));

            self::_log('Request:');
            self::_log($SoapClient->__getLastRequest());
            self::_log('Response:');
            self::_log($SoapClient->__getLastResponse());

            if ($Result->GetLicensesResult == true && $Result->ErrorCode == 0) {
                return $Result->Licenses->stLicense;

                foreach ($Result->Licenses->stLicense as $license) {
                    echo "\n<br />" . '***';					
                    echo "\n<br />" . $license->ID;
                    echo "\n<br />" . $license->Name;
                    echo "\n<br />" . $license->LicType;
                }
            }
        } catch (SoapFault $Exception) {
            self::_log($Exception->getMessage());
            return 'SOAP_EXCEPTION';
        }
    }

    public static function getLicensesLabels($_product) {
        $stLicenses = self::getLicenses();
        foreach ($stLicenses as $stLicense) {
            if ($stLicense->ID == $_product->getData('license_id') || $stLicense->ID == $_product->getData('est_license_id')) {
                echo 'label' . $stLicense->Name;
            }
        }
    }

    public static function checkPreviewExists($strProjectID) {
        self::_log('checkPreviewExists');

        $wsdl = Mage::getStoreConfig('own_drm/drm/drm_wsdl');
        $SoapClient = new SoapClient($wsdl, array('soap_version' => SOAP_1_3,
            'trace' => true,
            'classmap' => array('CheckPreviewExists' => 'Own_Drm_Model_Drm_Soap_Response_CheckPreviewExists'),
            'cache_wsdl' => WSDL_CACHE_NONE
                )
        );
        $soapRequest = array();
        $soapRequest['EMail'] = Mage::getStoreConfig('own_drm/drm/drm_email');
        $soapRequest['Password'] = Mage::getStoreConfig('own_drm/drm/drm_password');
        $soapRequest['ProjectID'] = $strProjectID;

        try {
            $Result = $SoapClient->__call('CheckPreviewExists', array('parameters' => $soapRequest));

            self::_log('Request:');
            self::_log($SoapClient->__getLastRequest());
            self::_log('Response:');
            self::_log($SoapClient->__getLastResponse());

            if ($Result->CheckPreviewExistsResult == true) {
                return $Result->CheckPreviewExistsResult;
            }
        } catch (SoapFault $Exception) {
            self::_log($Exception->getMessage());
            return 'SOAP_EXCEPTION';
        }
    }

    /**     * **************************************************************************** * */
    /**     * *************************** logging-functions ****************************** * */

    /**     * **************************************************************************** * */
    private static function _log($strLogText) {
	
        Mage::log($strLogText, null, self::LOGFILE);
    }

    private static function _logdrm($iIdOrder, $iIdStore, $iIdCustomer, $strRequest, $strResponse, $iErrorCode) {
        $DrmOrders = Mage::getModel('drm/orders');
        $DrmOrders->setOrderId($iIdOrder);
        $DrmOrders->setStoreId($iIdStore);
        $DrmOrders->setCustomerId($iIdCustomer);
        $DrmOrders->setRequest($strRequest);
        $DrmOrders->setResponse($strResponse);
        $DrmOrders->setError(self::$errors[$iErrorCode]);
        $DrmOrders->save();
    }

    public static function prepareLoginEx($strCustomerMail, $strCustomerPasswordHash) {
        $wsdl = Mage::getStoreConfig('own_drm/drm/drm_wsdl');
        $SoapClient = new SoapClient($wsdl, array('soap_version' => SOAP_1_3,
            'trace' => true,
            'classmap' => array('PrepareLogin' => 'Own_Drm_Model_Drm_Soap_Response_PrepareLogin'),
            'cache_wsdl' => WSDL_CACHE_NONE
                )
        );

        $soapRequest = array();
        $soapRequest['EMail'] = Mage::getStoreConfig('own_drm/drm/drm_email');
        $soapRequest['Password'] = Mage::getStoreConfig('own_drm/drm/drm_password');
        $soapRequest['ThemeID'] = Mage::getStoreConfig('own_drm/drm/drm_theme_id');

        $Doc = new DOMDocument('1.0', 'UTF-8');
        $Doc->formatOutput = true;
        $_Customer = $Doc->createElement('Customer');
        $Doc->appendChild($_Customer);

        $_EMail = $Doc->createElement('EMail', $strCustomerMail);
        $_Customer->appendChild($_EMail);

        $_Password = $Doc->createElement('PasswordHash', $strCustomerPasswordHash);
        $_Customer->appendChild($_Password);

        $soapRequest['XML'] = $Doc->saveXML();

        try {
            $Result = $SoapClient->__call('PrepareLoginEx', array('parameters' => $soapRequest));
            if ($Result->PrepareLoginExResult == true && $Result->ErrorCode == 0) {

                return $Result->sURL;
            } else {

                return $Result->ErrorCode;
            }
        } catch (SoapFault $Exception) {
            self::_log($Exception->getMessage());
            return 'SOAP_EXCEPTION';
        }
    }

}
