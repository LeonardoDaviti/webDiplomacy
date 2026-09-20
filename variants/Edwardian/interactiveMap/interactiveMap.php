<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of IAmap
 *
 * @author tobi
 */
class CoastConvoyOrders_IAmap extends IAmap 
{
        protected function jsFooterScript() {
                global $Variant;
                
                parent::jsFooterScript();
                
                libHTML::$footerScript[] = 'loadCoastConvoyOrders(Array("'.implode('","', $Variant->convoyCoasts) /* LOCAL (issue 009 wave 3): implode($array,$glue) was removed in PHP 8 */.'"))';
        }
}

class EdwardianVariant_IAmap extends CoastConvoyOrders_IAmap {}

?>
