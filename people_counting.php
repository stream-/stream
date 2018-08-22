<?php
class PCValues
{
    function __construct($isRemoveNull)
    {
        $this->values = array();
        $this->isRemoveNull = $isRemoveNull;
        $this->isLicensed = acquire_license("PC");
    }

    public function build($config)
    {
        if (isset($this->values))
        {
            $settings = $config->PCSettings;

            $isEnabled = $settings->Enable['value'] == 1;

            $channel = $config->Channel->ChannelID;
            $isJoin = $channel['join'] == 1;
            $isMultiEnabled = $channel['ml'] == 1;

            class_alias('RangeValidator', 'RValidator');

            $this->values['enable'] = new ValueParam($isEnabled && $this->isLicensed, 'bool_validator', 1000, "'enable' is changed");
            $this->values['licensed'] = new StaticParam($this->isLicensed);
            $this->values['store_abandonment_timeout'] = new XmlParam($settings->StoreAbandonmentTimeout, RValidator::Create(5, 3600), 'get_int', 1000, "'store_abandonment_timeout' is changed");
            $this->values['decision_timeout'] = new XmlParam($settings->MinIntersectionTime, RValidator::Create(0, 3600), 'get_int', 1000, "'decision_timeout' is changed");
            $this->values['multi_use'] = new ValueParam($isJoin && $isMultiEnabled, 'bool_validator', 1000, "'multi_use' is changed");
            $this->values['lines'] = new ValueParam($this->loadLines($config), array($this, 'validateLines') , 1000, "'lines' is changed");
        }
        return $this->values;
    }

    private function loadLines($config)
    {
        $lines = array();
        $this->lines = $config->xpath("Line");
        $this->config = $config;
        foreach($this->lines as $item)
        {
            $line = array();
            $line['name']           = (string)$item['id'];
            $line['nodes']          = string_to_polygon($item['nodes']);

            $line['direct_in']      = $item['filter'] != 2;
            $line['direct_out']     = $item['filter'] != 1;

            $line['master']         = $item['master'] == 1;
            $line['abandoned']      = $item['abandoned'] == 1;
            $line['blockopposite']  = $item['blockopposite'] == 1;
            $line['nonserviced']    = $item['nonserviced'] == 1;
            $line['buying_group']   = $item['buying_group'] == 1;
            $line['flow_count']     = $item['flow_count'] == 1;
            $line['flow_group_id']  = (string)$item['flow_group_id'];
            
            $line['report']         = is_null($item['report']) || $item['report'] == 1;
            $this->filtrateNull($line, $item);
            array_push($lines, $line);
        }
        return $lines;
    }

    private function filtrateNull(&$line, $item)
    {
        if ($this->isRemoveNull)
        {
            delete_if_not_exists($line, $item, array(
                'master'        => 'master',
                'abandoned'     => 'abandoned',
                'blockopposite' => 'blockopposite',
                'nonserviced'   => 'nonserviced',
                'report'        => 'report'
            ));
        }
    }

    public function validateLines(&$value)
    {
        $required = array(
                'name'      => 'applet_name_validator',
                'nodes'     => 'line_validator',
                'direct_in' => 'bool_validator',
                'direct_out'=> 'bool_validator',
        );
        $optional = array(
                'master'        => 'bool_validator',
                'abandoned'     => 'bool_validator',
                'blockopposite' => 'bool_validator',
                'nonserviced'   => 'bool_validator',
                'buying_group'  => 'bool_validator',
                'flow_count'    => 'bool_validator',
                'flow_group_id' => 'string_validator',
                'report'        => 'bool_validator'
        );
        if (is_array($value)) {
            foreach($value as &$line) {

                if (!is_array($line))
                    continue;

                // added default paramters to the line
                create_if_not_exists($line, array(
                    'master' => false,
                    'abandoned' => false,
                    'blockopposite' => false,
                    'nonserviced' => false,
                    'buying_group' => false,
                    'flow_count' => false,
                    'flow_group_id' => "",
                    'report' => true
                ));
            }
        }

        $validator = new CompositeValidator($required, $optional, "line");
        return $validator->test($value);
    }

    public function save($isChanged)
    {
        if (!$isChanged)
            return TRUE;

        $switch = $this->values['enable'];
        if ($switch->getStatus($c, $m))
        {
            if (!$this->isLicensed)
                return 'please, purchase a license to activate this service';
            $this->config->PCSettings->Enable['value'] = $switch->getValue();
        }

        $switch = $this->values['multi_use'];
        if ($switch->getStatus($c, $m))
        {
            $channelId = $this->config->Channel->ChannelID;
            if ($switch->getValue())
            {
                $channelId['join'] = 1;
                $channelId['ml'] = 1;
            } else {
                $channelId['ml'] = 0;
                if ( $channelId['mq'] == 0 && $channelId['mz'] == 0 )
                    $channelId['join'] = 0;
            }
        }

        $lines = $this->values['lines'];
        if ($lines->getStatus($c, $m))
        {
            $new = $lines->getValue();
            $this->lines = resize_array($this->config, $this->lines, "Line", count($new));
            for ($i = 0; $i < count($new); ++$i)
            {
                $line = $new[$i];
                $item = $this->lines[$i];
                $item['id'] = $line['name'];
                $item['nodes'] = polygon_to_string($line['nodes']);

                if ($line['direct_in'] && $line['direct_out'] )
                    $item['filter'] = 0;
                else if ($line['direct_in'])
                    $item['filter'] = 1;
                else if ($line['direct_out'])
                    $item['filter'] = 2;
                else
                    return "Invalid combination of the line[$i]'s properties: direct_in and direct_out are false";

                if ( $item['filter'] != 0 && $line['abandoned'] )
                    return "Invalid combination of the line[$i]'s properties: when the abandon feature is on both directions (in and out) must be allowed";

                if ( $item['filter'] == 0 && $line['blockopposite'] )
                    return "Invalid combination of the line[$i]'s properties: when the blockopposite feature is on only one direction must be allowed";

                array_copy($line, $item, array(
                    'master'        => 'master',
                    'abandoned'     => 'abandoned',
                    'blockopposite' => 'blockopposite',
                    'nonserviced'   => 'nonserviced',
                    'buying_group'  => 'buying_group',
                    'flow_count'    => 'flow_count',
                    'flow_group_id' => 'flow_group_id',
                    'report'        => 'report'
                ));
            }
        }

        return TRUE;
    }
}
?>
