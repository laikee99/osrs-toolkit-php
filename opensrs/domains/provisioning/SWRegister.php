<?php

namespace opensrs\domains\provisioning;

use opensrs\Base;
use opensrs\Exception;

class SWRegister extends Base
{
    public $action = 'sw_register';
    public $object = 'domain';

    public $_formatHolder = '';
    public $resultFullRaw;
    public $resultRaw;
    public $resultFullFormatted;
    public $resultFormatted;

    // Dynamic required fields per reg_type
    public $requiredFieldsByType = array(
        'new' => array(
            'domain',
            'custom_nameservers',
            'custom_tech_contact',
            'period',
            'reg_username',
            'reg_password',
            'reg_type',
        ),
        'transfer' => array(
            'domain',
            'auth_info',
            'reg_username',
            'reg_password',
            'reg_type',
            //'custom_transfer_nameservers' //is optional in API, enforced as domains existing if not set
        ),
        'assign' => array(
            'domain',
            'reg_username',
            'reg_password',
            'reg_type',
        ),
        'owner_change' => array(
            'domain',
            'reg_username',
            'reg_password',
            'reg_type',
        ),
    );

    public function __construct($formatString, $dataObject, $returnFullResponse = true)
    {
        parent::__construct();

        $this->_formatHolder = $formatString;

        $this->_validateObject($dataObject);

        $this->validateAttributes((array)$dataObject->attributes);

        $this->send($dataObject, $returnFullResponse);
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    private function validateAttributes($attributes)
    {
        if (!isset($attributes['reg_type'])) {
            throw new Exception('SWRegister Error - Missing required field: reg_type');
        }

        $regType = strtolower($attributes['reg_type']);

        if (!isset($this->requiredFieldsByType[$regType])) {
            throw new Exception("SWRegister Error - Unsupported reg_type: {$regType}");
        }

        foreach ($this->requiredFieldsByType[$regType] as $field) {
            if (!isset($attributes[$field])) {
                throw new Exception("SWRegister Error - Missing required field for '{$regType}': {$field}");
            }
        }

        // Special handling for transfer - ensure custom_transfer_nameservers is always present as an array
        if ($regType === 'transfer') {
            if (!isset($attributes['custom_transfer_nameservers'])) {
                $attributes['custom_transfer_nameservers'] = 0;
            }
        }

        // Example for .EU domain validation
        if (isset($attributes['domain']) && preg_match('/\.eu$/i', $attributes['domain'])) {
            if (empty($attributes['lang_pref'])) {
                throw new Exception("SWRegister Error - .EU domains require 'lang_pref'.");
            }
            if (empty($attributes['eu_country_of_residence']) && empty($attributes['eu_country_of_citizenship'])) {
                throw new Exception("SWRegister Error - .EU domains require 'eu_country_of_residence' or 'eu_country_of_citizenship'.");
            }
        }

        // You can extend here for other TLDs like .CA or .DE
    }

    public function customResponseHandling($arrayResult, $returnFullResponse = true)
    {
        if (isset($arrayResult['attributes']['forced_pending']) && $arrayResult['attributes']['forced_pending'] != '' && $arrayResult['is_success'] == 1) {
            $arrayResult['is_success'] = 0;

            if ($arrayResult['response_text'] == 'Registration successful') {
                $arrayResult['response_text'] = 'Insufficient Funds';
            }
        }

        return $arrayResult;
    }
}
