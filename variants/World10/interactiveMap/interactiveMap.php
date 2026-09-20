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

class MapName_IAmap extends IAmap 
{
        public function __construct($variant) {
                parent::__construct($variant, 'IA_map.png');
        }
}

class CoastConvoyOrders_IAmap extends MapName_IAmap 
{
        protected function jsFooterScript() {
                global $Variant;
                
                parent::jsFooterScript();
                
                libHTML::$footerScript[] = 'loadCoastConvoyOrders(Array("'.implode('","', $Variant->convoyCoasts) /* LOCAL DEVIATION (issue 009 wave 5): implode($array,$glue) argument order was removed in PHP 8 */.'"))';
        }
}

class World10Variant_IAmap extends CoastConvoyOrders_IAmap {}

?>
