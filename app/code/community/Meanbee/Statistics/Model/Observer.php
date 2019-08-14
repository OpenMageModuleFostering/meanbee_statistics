<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@meanbee.com so we can send you a copy immediately.
 *
 * @category   Meanbee
 * @package    Meanbee_Statistics
 * @copyright  Copyright (c) 2009 Meanbee Internet Solutions Limited (http://www.meanbee.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @version    $Id$
 */

final class Meanbee_Statistics_Model_Observer {
    public function __construct() {
        $this->_debugFlag = (bool) Mage::getStoreConfig('admin/statistics/debug');
    }

    protected $_debugFlag = false;

    public function observe() {
        $session = Mage::getSingleton('core/session');

        $enabled = Mage::getStoreConfig('admin/statistics/active');
        $notified = $session->getStatsNotified();

        $this->_debug('Enabled: ' . (bool) $enabled);
        $this->_debug('Notified: ' . (bool) $notified);

        if ($enabled && !$notified) {
            $config = Mage::registry('config');

            $data = array();
            $data = array_merge($data, $this->_standardInformation($config));
            $data = array_merge($data, $this->_moduleInformation($config));   

            try {
                $this->_notifyServer($data);
                $session->setStatsNotified(true);
                $this->_debug('Notification successful.');
            } catch (Exception $e) {
                // Be quiet, theres no reason to tell anyone, but
                // we will try on the next page load
                $this->_debug('There was an exception ' . $e->getMessage());
            }
        } else {
            $this->_debug('Not notified, already done this session.');
        }
    }

    /**
     * @param Mage_Core_Model_Config $config
     * @return array
     */
    protected function _standardInformation(&$config) {
        return array(
            'now'           => time(),
            'install_date'  => strtotime((string) $config->getNode('global/install/date')),
            'country'       => Mage::getStoreConfig('general/country/default'),
            'local'         => Mage::getStoreConfig('general/locale/code'),
            'currency'      => Mage::getStoreConfig('currency/options/base')
        );
    }

    /**
     * @param Mage_Core_Model_Config $config
     * @return array
     */
    protected function _moduleInformation(&$config) {
        return $config->getNode('global/statistics')->asArray();
    }

    /**
     * @param array $data
     */
    protected function _notifyServer($data) {
        $client = new Zend_Http_Client('https://secure.meanbee.com/magento_notify/notify.php');

        $client->setConfig(array(
            'timeout' => 3
        )); // Nice and sort, don't want to disturb the admin user
        $client->setMethod(Zend_Http_Client::POST);
        $client->setParameterPost($data);
        $response = $client->request();
        
        if ($response->isSuccessful()) {
            return true;
        } else {
            Mage::throwException('Failed to contact notify server.');
        }
    }

    protected function _debug($message) {
        if ($this->_debugFlag) {
            echo "Meanbee_Statistics: $message<br />";
        }
    }
}
