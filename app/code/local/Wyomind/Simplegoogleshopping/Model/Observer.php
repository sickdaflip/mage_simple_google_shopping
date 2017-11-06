<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
class Wyomind_Simplegoogleshopping_Model_Observer
{
    public function generateFeeds()
    {
        $log = array();
        $log[] = "-------------------- CRON PROCESS --------------------";

        $collection = Mage::getModel('simplegoogleshopping/simplegoogleshopping')->getCollection();
        $cnt = 0;

        foreach ($collection as $feed) {
            try {
                $log[] = "--> Running profile : " . $feed->getSimplegoogleshoppingFilename() 
                        . ' [#' . $feed->getSimplegoogleshoppingId() . '] <--';

                $cron['curent']['localDate'] = Mage::getSingleton('core/date')->date('l Y-m-d H:i:s');
                $cron['curent']['gmtDate'] = Mage::getSingleton('core/date')->gmtDate('l Y-m-d H:i:s');
                $cron['curent']['localTime'] = Mage::getSingleton('core/date')->timestamp();
                $cron['curent']['gmtTime'] = Mage::getSingleton('core/date')->gmtTimestamp();

                $cron['file']['localDate'] = Mage::getSingleton('core/date')
                                                ->date('l Y-m-d H:i:s', $feed->getSimplegoogleshoppingTime());
                $cron['file']['gmtDate'] = $feed->getSimplegoogleshoppingTime();
                $cron['file']['localTime'] = Mage::getSingleton('core/date')
                                                ->timestamp($feed->getSimplegoogleshoppingTime());
                $cron['file']['gmtTime'] = Mage::getSingleton('core/date')
                                            ->gmtTimestamp($feed->getSimplegoogleshoppingTime());

                /**
                 * Magento getGmtOffset() is bugged and doesn't include daylight saving time,
                 * the following workaround is used 
                 */
                // date_default_timezone_set(Mage::app()->getStore()->getConfig('general/locale/timezone'));
                // $date = new DateTime();
                //$cron['offset'] = $date->getOffset() / 3600;
                $cron['offset'] = Mage::getSingleton('core/date')->getGmtOffset("hours");
                
                $log[] = '   * Last update : ' . $cron['file']['gmtDate'] . " GMT / " 
                            . $cron['file']['localDate'] . ' GMT' . $cron['offset'];
                $log[] = '   * Current date : ' . $cron['curent']['gmtDate'] . " GMT / " 
                            . $cron['curent']['localDate'] . ' GMT' . $cron['offset'];

                $cronExpr = json_decode($feed->getCronExpr());
                $i = 0;
                $done = false;

                foreach ($cronExpr->days as $d) {
                    foreach ($cronExpr->hours as $h) {
                        $time = explode(':', $h);
                        if (date('l', $cron['curent']['gmtTime']) == $d) {
                            $cron['tasks'][$i]['localTime'] = strtotime(Mage::getSingleton('core/date')->date('Y-m-d')) 
                                                                + ($time[0] * 60 * 60) + ($time[1] * 60);
                            $cron['tasks'][$i]['localDate'] = date('l Y-m-d H:i:s', $cron['tasks'][$i]['localTime']);
                        } else {
                            $cron['tasks'][$i]['localTime'] = strtotime("last " . $d, $cron['curent']['localTime']) 
                                                                + ($time[0] * 60 * 60) + ($time[1] * 60);
                            $cron['tasks'][$i]['localDate'] = date('l Y-m-d H:i:s', $cron['tasks'][$i]['localTime']);
                        }

                        if ($cron['tasks'][$i]['localTime'] >= $cron['file']['localTime'] &&
                            $cron['tasks'][$i]['localTime'] <= $cron['curent']['localTime'] &&
                            $done != true
                        ) {
                            $log[] = '   * Scheduled : ' . ($cron['tasks'][$i]['localDate'] . " GMT" . $cron['offset']);

                            if ($feed->generateXml()) {
                                if (Mage::helper("core")->isModuleEnabled("Wyomind_Googlemerchantpromotions")) {
                                    if ($feed->getSimplegoogleshoppingPromotions()) {
                                        $prefix = Mage::getStoreConfig('googlemerchantpromotions/settings/prefix');
                                        $suffix = Mage::getStoreConfig('googlemerchantpromotions/settings/suffix');
                                        $promoFileName = $prefix
                                                . str_replace(".xml", "", $feed->getSimplegoogleshoppingFilename()) 
                                                . $suffix
                                                . ".xml";
                                        Mage::helper("googlemerchantpromotions")
                                                ->generateDatafeed($promoFileName, $feed);
                                    }
                                }
                                $done = true;
                                $cnt++;
                                
                                $log[] = '   * EXECUTED!';
                            }
                        }

                        $i++;
                    }
                }
            } catch (Exception $e) {
                $log[] = '   * ERROR! ' . ($e->getMessage());
            }
            if (!$done) {
                $log[] = '   * SKIPPED!';
            }
        }

        if (Mage::getStoreConfig("simplegoogleshopping/setting/enable_report")) {
            foreach (explode(',', Mage::getStoreConfig("simplegoogleshopping/setting/emails")) as $email) {
                try {
                    if ($cnt) {
                        // start test code
                        $mail = Mage::getSingleton('core/email');
                        $mail->setToEmail($email);
                        $mail->setSubject(Mage::getStoreConfig("simplegoogleshopping/setting/report_title"));
                        $mail->setBody("\n" . implode($log, "\n"));
                        $mail->setFromEmail(Mage::getStoreConfig('trans_email/ident_general/email'));
                        $mail->setFromName(Mage::getStoreConfig('trans_email/ident_general/name'));
                        $mail->send();
                    }
                } catch (Exception $e) {
                    $log[] = '   * EMAIL ERROR! ' . ($e->getMessage());
                }
            }
        }
        
        Mage::log("\n" . implode($log, "\n"), null, "SimpleGoogleShopping-cron.log");
    }

    public function switchCurrency()
    {
        if ($curency = (string) Mage::app()->getRequest()->getParam('currency')) {
            Mage::app()->getStore()->setCurrentCurrencyCode($curency);
        }
    }
}
