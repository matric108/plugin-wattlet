<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class wattlet extends eqLogic {

    /*     * ***********************Methode static*************************** */
    
    
	public static function getBridgeStatus() {
		$bridgeIp = config::byKey('bridge_ip', 'wattlet');
		$request_http = new com_http('http://' . $bridgeIp . '/status.json');
		return json_decode(trim($request_http->exec()), true);
	}
	
	public static function updateValues() {
		$wattcubes = wattlet::getBridgeStatus();
		$wattcubes = $wattcubes['modules'];
		foreach($wattcubes as $wattcube) {
			$eqLogic = eqLogic::byLogicalId($wattcube['address'],'wattlet');
			if(is_array($wattcube['state'])){
				$cmd_io1=cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), 'state_io1');
				if($cmd_io1->execCmd() <> $wattcube['state']['io1']){
					$cmd_io1->event($wattcube['state']['io1']);
					log::add('wattlet','debug','MAJ equipement ' . $eqLogic->getName() . ' etat 1 :' . $wattcube['state']['io1']);
				}
				$cmd_io2=cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), 'state_io2');
				if($cmd_io2->execCmd() <> $wattcube['state']['io2']){
					$cmd_io2->event($wattcube['state']['io2']);
					log::add('wattlet','debug','MAJ equipement ' . $eqLogic->getName() . ' etat 2 :' . $wattcube['state']['io2']);
				}								
			}else{
				$cmd_io=cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), 'state');
				if($cmd_io->execCmd() <> $wattcube['state']){
					$cmd_io->event($wattcube['state']);
					log::add('wattlet','debug','MAJ equipement ' . $eqLogic->getName() . ' etat :' . $wattcube['state']);
				}
				
			}
		}
	}
    
    public static function searchwattletDevices() {
    	$wattcubes = wattlet::getBridgeStatus();
		$wattcubes = $wattcubes['modules'];
		log::add('wattlet','debug','fichier : ' . json_encode($wattcubes) );
		foreach($wattcubes as $wattcube) {
			$eqLogics = eqLogic::byTypeAndSearhConfiguration('wattlet',$wattcube['address']);
			if(count($eqLogics) == 0){
				$eqLogic = new eqLogic();
	            $eqLogic->setEqType_name('wattlet');
	            $eqLogic->setIsEnable(1);
	            $eqLogic->setName($wattcube['name']);
				$eqLogic->setLogicalId($wattcube['address']);
	            $eqLogic->setConfiguration('name',$wattcube['name']);
	            $eqLogic->setConfiguration('address','00' . $wattcube['address']);
				$eqLogic->setConfiguration('soft',$wattcube['soft']);
				$eqLogic->setConfiguration('hard',$wattcube['hard']);
	            $eqLogic->setConfiguration('type',$wattcube['type']);
				$eqLogic->setIsVisible(1);
	            $eqLogic->save();				
			}
		}
		$eqLogics = eqLogic::byType('wattlet');
		if(count($eqLogics) > 0){
			foreach($eqLogics as $eqLogic) {
				$key = array_search($eqLogic->getLogicalId(), array_column($wattcubes, 'address'));
				if (!is_numeric($key)){
					$eqLogic->remove();
					log::add('wattlet','info',' Suppression de l\'équipement ayant l\'adresse : ' . $eqLogic->getLogicalId());
				}
				
			}
		}	        
    }
	
	public function postSave() {
		$type = $this->getConfiguration('type');		
		if($type =="LIGHT"){
			$wattletCmd = $this->getCmd(null, 'state');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Etat', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('state');
			$wattletCmd->setConfiguration('request', 'state');
			$wattletCmd->setType('info');
			$wattletCmd->setSubType('binary');
			$wattletCmd->setIsVisible(false);
			$wattletCmd->setDisplay('generic_type','LIGHT_STATE');
			$wattletCmd->save();
			$stateId = $wattletCmd->getId();
			
			$wattletCmd = $this->getCmd(null, 'on');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('On', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('on');
			$wattletCmd->setConfiguration('request', '01');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setTemplate('dashboard','light');
			$wattletCmd->setTemplate('mobile','light');
			$wattletCmd->setDisplay('generic_type','LIGHT_ON');
			$wattletCmd->setValue($stateId);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'off');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Off', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('off');
			$wattletCmd->setConfiguration('request', '00');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setTemplate('dashboard','light');
			$wattletCmd->setTemplate('mobile','light');
			$wattletCmd->setDisplay('generic_type','LIGHT_OFF');
			$wattletCmd->setValue($stateId);
			$wattletCmd->save();
		}else if($type =="OPEN"){
			$wattletCmd = $this->getCmd(null, 'state');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Etat', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('state');
			$wattletCmd->setConfiguration('request', 'state');
			$wattletCmd->setType('info');
			$wattletCmd->setSubType('binary');
			$wattletCmd->setIsVisible(false);
			$wattletCmd->setDisplay('generic_type','LIGHT_STATE');
			$wattletCmd->save();
			$stateId = $wattletCmd->getId();
			
			$wattletCmd = $this->getCmd(null, 'on');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('On', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('on');
			$wattletCmd->setConfiguration('request', '01');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setTemplate('dashboard','light');
			$wattletCmd->setTemplate('mobile','light');
			$wattletCmd->setDisplay('generic_type','LIGHT_ON');
			$wattletCmd->setValue($stateId);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'off');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Off', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('off');
			$wattletCmd->setConfiguration('request', '00');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setTemplate('dashboard','light');
			$wattletCmd->setTemplate('mobile','light');
			$wattletCmd->setDisplay('generic_type','LIGHT_OFF');
			$wattletCmd->setValue($stateId);
			$wattletCmd->save();
		}elseif($type=="LIGHT-2"){
			$wattletCmd = $this->getCmd(null, 'state_io1');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Etat 1', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('state_io1');
			$wattletCmd->setConfiguration('request', 'state_io1');
			$wattletCmd->setType('info');
			$wattletCmd->setSubType('binary');
			$wattletCmd->setIsVisible(false);
			$wattletCmd->setDisplay('generic_type','LIGHT_STATE');
			$wattletCmd->save();
			$stateId1 = $wattletCmd->getId();
			
			$wattletCmd = $this->getCmd(null, 'on1');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('On 1', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('on1');
			$wattletCmd->setConfiguration('request', '01');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setTemplate('dashboard','light');
			$wattletCmd->setTemplate('mobile','light');
			$wattletCmd->setDisplay('generic_type','LIGHT_ON');
			$wattletCmd->setValue($stateId1);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'off1');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Off 1', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('off1');
			$wattletCmd->setConfiguration('request', '00');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setTemplate('dashboard','light');
			$wattletCmd->setTemplate('mobile','light');
			$wattletCmd->setDisplay('generic_type','LIGHT_OFF');
			$wattletCmd->setValue($stateId1);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'state_io2');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Etat 2', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('state_io2');
			$wattletCmd->setConfiguration('request', 'state_io2');
			$wattletCmd->setType('info');
			$wattletCmd->setSubType('binary');
			$wattletCmd->setIsVisible(false);
			$wattletCmd->setDisplay('generic_type','LIGHT_STATE');
			$wattletCmd->save();
			$stateId2 = $wattletCmd->getId();
			
			$wattletCmd = $this->getCmd(null, 'on2');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('On 2', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('on2');
			$wattletCmd->setConfiguration('request', '03');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setTemplate('dashboard','light');
			$wattletCmd->setTemplate('mobile','light');
			$wattletCmd->setDisplay('generic_type','LIGHT_ON');
			$wattletCmd->setValue($stateId2);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'off2');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Off 2', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('off2');
			$wattletCmd->setConfiguration('request', '02');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setTemplate('dashboard','light');
			$wattletCmd->setTemplate('mobile','light');
			$wattletCmd->setDisplay('generic_type','LIGHT_OFF');
			$wattletCmd->setValue($stateId2);
			$wattletCmd->save();
					
		}elseif($type=="POWER"){
			$wattletCmd = $this->getCmd(null, 'state_io1');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Etat Entrée', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('state_io1');
			$wattletCmd->setConfiguration('request', 'state_io1');
			$wattletCmd->setType('info');
			$wattletCmd->setSubType('binary');
			$wattletCmd->setDisplay('generic_type','LIGHT_STATE');
			$wattletCmd->save();
			$stateId1 = $wattletCmd->getId();
			
			$wattletCmd = $this->getCmd(null, 'on1');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('On Entrée', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('on1');
			$wattletCmd->setConfiguration('request', '01');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setOrder(2);
			$wattletCmd->setDisplay('generic_type','LIGHT_ON');
			$wattletCmd->setValue($stateId1);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'off1');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Off Entrée', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('off1');
			$wattletCmd->setConfiguration('request', '00');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setOrder(1);
			$wattletCmd->setDisplay('generic_type','LIGHT_OFF');
			$wattletCmd->setValue($stateId1);
			$wattletCmd->save();
					
			$wattletCmd = $this->getCmd(null, 'state_io2');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Etat Sortie', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('state_io2');
			$wattletCmd->setConfiguration('request', 'state_io2');
			$wattletCmd->setType('info');
			$wattletCmd->setSubType('binary');
			$wattletCmd->setIsVisible(false);
			$wattletCmd->setDisplay('generic_type','LIGHT_STATE');
			$wattletCmd->save();
			$stateId2 = $wattletCmd->getId();
			
			$wattletCmd = $this->getCmd(null, 'on2');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('On Sortie', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('on2');
			$wattletCmd->setConfiguration('request', '03');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setOrder(3);
			$wattletCmd->setTemplate('dashboard','light');
			$wattletCmd->setTemplate('mobile','light');
			$wattletCmd->setDisplay('forceReturnLineBefore', 1);
			$wattletCmd->setDisplay('generic_type','LIGHT_ON');
			$wattletCmd->setValue($stateId2);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'off2');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Off Sortie', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('off2');
			$wattletCmd->setConfiguration('request', '04');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setOrder(4);
			$wattletCmd->setTemplate('dashboard','light');
			$wattletCmd->setTemplate('mobile','light');
			$wattletCmd->setDisplay('forceReturnLineBefore', 1);
			$wattletCmd->setDisplay('generic_type','LIGHT_OFF');
			$wattletCmd->setValue($stateId2);
			$wattletCmd->save();
					
		}elseif($type=="PUSH" || $type=="PUSH-L"){
			$wattletCmd = $this->getCmd(null, 'state');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Etat', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('state');
			$wattletCmd->setConfiguration('request', 'state');
			$wattletCmd->setType('info');
			$wattletCmd->setSubType('binary');
			$wattletCmd->setDisplay('generic_type','LIGHT_STATE');
			$wattletCmd->save();
			$stateId = $wattletCmd->getId();
			
			$wattletCmd = $this->getCmd(null, 'on');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('On', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('on');
			$wattletCmd->setConfiguration('request', '01');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setDisplay('generic_type','LIGHT_ON');
			$wattletCmd->setValue($stateId);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'off');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Off', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('off');
			$wattletCmd->setConfiguration('request', '00');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setDisplay('generic_type','LIGHT_OFF');
			$wattletCmd->setValue($stateId);
			$wattletCmd->save();
					
		}elseif($type=="PUSH-2"){
			$wattletCmd = $this->getCmd(null, 'state_io1');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Etat 1', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('state_io1');
			$wattletCmd->setConfiguration('request', 'state_io1');
			$wattletCmd->setType('info');
			$wattletCmd->setSubType('binary');
			$wattletCmd->setDisplay('generic_type','LIGHT_STATE');
			$wattletCmd->save();
			$stateId1 = $wattletCmd->getId();
			
			$wattletCmd = $this->getCmd(null, 'on1');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('On 1', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('on1');
			$wattletCmd->setConfiguration('request', '01');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setOrder(2);
			$wattletCmd->setDisplay('generic_type','LIGHT_ON');
			$wattletCmd->setValue($stateId1);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'off1');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Off 1', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('off1');
			$wattletCmd->setConfiguration('request', '00');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setOrder(1);
			$wattletCmd->setDisplay('generic_type','LIGHT_OFF');
			$wattletCmd->setValue($stateId1);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'state_io2');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Etat 2', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('state_io2');
			$wattletCmd->setConfiguration('request', 'state_io2');
			$wattletCmd->setType('info');
			$wattletCmd->setSubType('binary');
			$wattletCmd->setDisplay('generic_type','LIGHT_STATE');
			$wattletCmd->save();
			$stateId2 = $wattletCmd->getId();
			
			$wattletCmd = $this->getCmd(null, 'on2');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('On 2', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('on2');
			$wattletCmd->setConfiguration('request', '03');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setOrder(4);
			$wattletCmd->setDisplay('generic_type','LIGHT_ON');
			$wattletCmd->setValue($stateId2);
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'off2');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Off 2', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('off2');
			$wattletCmd->setConfiguration('request', '02');
			$wattletCmd->setType('action');
			$wattletCmd->setSubType('other');
			$wattletCmd->setOrder(3);
			$wattletCmd->setDisplay('generic_type','LIGHT_OFF');
			$wattletCmd->setValue($stateId2);
			$wattletCmd->save();
					
		}elseif($type=="WIN"){
			$wattletCmd = $this->getCmd(null, 'state');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Etat', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('state');
			$wattletCmd->setConfiguration('request', 'state');
			$wattletCmd->setType('info');
			$wattletCmd->setOrder(1);
			$wattletCmd->setSubType('binary');
			$wattletCmd->setTemplate('dashboard','store');
			$wattletCmd->setTemplate('mobile','store');
			$wattletCmd->setDisplay('generic_type','FLAP_STATE');
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'slider');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Niveau', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('slider');
			$wattletCmd->setConfiguration('request', 'slider');
			$wattletCmd->setConfiguration('minValue', 0);
			$wattletCmd->setConfiguration('maxValue', 8);
			$wattletCmd->setType('action');
			$wattletCmd->setOrder(2);
			$wattletCmd->setSubType('slider');
			$wattletCmd->setDisplay('generic_type','FLAP_SLIDER');
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'up');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Monter', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('up');
			$wattletCmd->setConfiguration('request', '00');
			$wattletCmd->setType('action');
			$wattletCmd->setOrder(3);
			$wattletCmd->setSubType('other');
			$wattletCmd->setDisplay('generic_type','FLAP_UP');
			$wattletCmd->setDisplay('icon','<i class="fa fa-arrow-up"></i>');
			$wattletCmd->save();
			
			$wattletCmd = $this->getCmd(null, 'down');
			if (!is_object($wattletCmd)) {
				$wattletCmd = new wattletCmd();
				$wattletCmd->setName(__('Descendre', __FILE__));
			}
			$wattletCmd->setEqLogic_id($this->getId());
			$wattletCmd->setLogicalId('down');
			$wattletCmd->setConfiguration('request', '08');
			$wattletCmd->setType('action');
			$wattletCmd->setOrder(4);
			$wattletCmd->setSubType('other');
			$wattletCmd->setDisplay('generic_type','FLAP_DOWN');
			$wattletCmd->setDisplay('icon','<i class="fa fa-arrow-down"></i>');
			$wattletCmd->save();
					
		}
	}
	public static function cron15($_eqLogic_id = null) {
		wattlet::updateValues();
	}
}

class wattletCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    public function preSave() {
        if ($this->getConfiguration('request') == '') {
        	throw new Exception(__('La requete ne peut etre vide',__FILE__));
        }
    }

    public function execute($_options = null) {
    	log::add('wattlet', 'info', 'Debut de l action');	
		$wattlet=$this->getEqLogic();
		$bridgeIp = config::byKey('bridge_ip', 'wattlet');
		if ($this->type == 'action') {
			if($wattlet->getConfiguration('TYPE') == "LIGHT-2" || $wattlet->getConfiguration('TYPE') == "POWER" || $wattlet->getConfiguration('TYPE') == "PUSH-2"){
				if($this->getConfiguration('request') == "00" || $this->getConfiguration('request') == "01"){
					$state_io = cmd::byEqLogicIdAndLogicalId($wattlet->getId(), 'state_io2');
					if($state_io->getCmd() == 0){
						$request = $this->getConfiguration('request');
					}else{
						 if($this->getConfiguration('request') == '01'){
						 	$request = "03";
						 }else{
						 	$request = "02";
						 }
					}					
				}else{
					$state_io = cmd::byEqLogicIdAndLogicalId($wattlet->getId(), 'state_io1');
					if($state_io->getCmd() == 0){
						if($this->getConfiguration('request') == '02'){
						 	$request = "00";
						 }else{
						 	$request = "01";
						 }
					}else{
						 if($this->getConfiguration('request') == '03'){
						 	$request = "03";
						 }else{
						 	$request = "02";
						 }
					}
				}
			}elseif($wattlet->getConfiguration('TYPE') == "WIN" && $this->getSubType() == "slider"){
				$request = $_options['slider'];
			}else{
				$request = $this->getConfiguration('request');
			}
			log::add('wattlet','debug','adresse : ' . 'http://' . $bridgeIp . '/' . $wattlet->getConfiguration('address') . '/wrio/' . $request);
			$request_http = new com_http('http://' . $bridgeIp . '/' . $wattlet->getConfiguration('address') . '/wrio/' . $request);
			$result = $request_http->exec();
			sleep(1);
			wattlet::updateValues();			
			return true;
		}else{
			return $this->getValue();
		}
	}
}
?>