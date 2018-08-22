<?php
class CVValues
{
    const LOCK_TILT_PAN_HEIGHT_STATE = '/data/Raptor/lock_tilt_pan_height_state';
    const SCHEDULER_CONFIG = '/data/Raptor/rv_scheduler.xml'; //only for auto fw update
    const ENTER_AREA = '/tmp/cv_entering_area';
    const COUNTING_CONFIG = '/data/Raptor/counting.xml'; //only for steady_track_recovery

    function __construct($filename)
    {
        $this->values = null;
        $this->filename = $filename;
        if($this->hwConfig = simplexml_load_file($filename))
            $this->values= array();
    }

    public function build($libConfig)
    {
        if (isset($this->values))
        {
            /* GET scheduler config file
             */
            if (!($this->schedulerConfig = simplexml_load_file(self::SCHEDULER_CONFIG)))
                return ErrorController::internalErrorAction('/data/Raptor/rv_scheduler.xml READ XML');

            /* GET CV STATE
             */
            $rv_state = load_state('/tmp/rv_state');
            $cv = $rv_state['CV'];
            $isCVEnabled = (isset($cv) && $cv[1] === 0);
            unset($rv_state);

            if (check_ea_status(self::ENTER_AREA) === FALSE)
                return ErrorController::errorAction( 2000, "202 Accepted", "EnterArea is not ready");

            /* CV LIB PARAMETERS
             */
            $maskParams = $libConfig->MaskParams;
            $this->values['use_invalid_regions']  = new XmlParam($maskParams->useInvalidPolygonArea ,'bool_validator',   'get_bool',    1000, "'use_invalid_regions' is changed");
            $this->values['invalid_regions']      = new RegionParam($maskParams->invalidPolygonsList, 'regions_validator','get_polygons',1000, "'invalid_regions' is changed");
            $this->values['use_roi_regions']      = new XmlParam($maskParams->useROIPolygonArea ,  'bool_validator',   'get_bool',    1000, "'use_roi_regions' is changed");
            $this->values['roi_regions']          = new RegionParam($maskParams->roiList, 'regions_validator','get_polygons', 1000, "'roi_regions' is changed");
            $this->values['enter_area']           = new EnterAreaParam($this->loadEnterArea($libConfig->DepthObjectTracker->enterArea, self::ENTER_AREA), 'enter_area_validator', 1000, "'enter_area' is changed");
            $this->values['steady_track_recovery']= new SteadyTrackRecoveryParam($this->loadSteadyTrackRecovery($libConfig->DepthObjectTracker->trackRecovery->steadyTrackRecovery), 'steady_track_recovery_validator', 1000, "'steady_track_recovery' is changed");
            $this->values['floor_anchors']        = new AnchorsParam($libConfig->AnchorPoints, 'anchors_validator', 'get_anchors', 1000, "'floor_anchors' is changed", "set_anchors");

            /* Tilt, Pan and Height parameters
             */
            $detectionParams = $libConfig->TopViewHumanDetection;
            $lock = false;
            if ( file_exists( self::LOCK_TILT_PAN_HEIGHT_STATE ) )
                $lock = true;
            $this->values['lock_tilt_pan_height'] = new ValueParam($lock, 'bool_validator', 1000, "'lock_tilt_pan_height' is changed");
            $this->values['use_tilt_pan']         = new XmlParam($detectionParams->doTiltCorrection,   'bool_validator', 'get_bool', 1000, "'use_tilt_pan' is changed");

            $tiltParams = $libConfig->TiltCorrection;
            $tilt          = floatval($tiltParams->userTilt['value']);
            $pan           = floatval($tiltParams->userPan['value']);
            $forceTiltPan  = get_bool($tiltParams->forceUserTiltPan);
            if ( $forceTiltPan === FALSE ) {
                // if auto mode
                $tilt = SensorStatusResource::readInteger( '/tmp/cv2www.cam_tilt', $isCVEnabled );
                $pan = SensorStatusResource::readInteger( '/tmp/cv2www.cam_pan', $isCVEnabled );
            }
            $autoTiltPan = !$forceTiltPan;
            if ( file_exists( self::LOCK_TILT_PAN_HEIGHT_STATE ) )
                $autoTiltPan = !boolval(get_specific_value(self::LOCK_TILT_PAN_HEIGHT_STATE, 2, "forceUserTiltPan: "));
            $this->values['tilt_deg']      = new TiltParam( $tilt, $tiltParams->userTilt, "'tilt_deg' is changed" );
            $this->values['pan_deg']       = new PanParam( $pan, $tiltParams->userPan, "'pan_deg' is changed" );
            $this->values['auto_tilt_pan'] = new AutoTiltPanParam( $autoTiltPan, $tiltParams->forceUserTiltPan, "'auto_tilt_pan' is changed" );

            $userHeight = $detectionParams->userHeight;
            $height = floatval($userHeight['value']) * 100;
            $isAutoHeight = ( $height == 0 );
            if ( $isAutoHeight ) {
                $height = SensorStatusResource::readInteger('/tmp/cv2www.cam_height', $isCVEnabled);
            }
            if ( file_exists( self::LOCK_TILT_PAN_HEIGHT_STATE ) )
                $isAutoHeight = ( intval(get_specific_value(self::LOCK_TILT_PAN_HEIGHT_STATE, 3, "userHeight: ")) == 0 );

            $this->values['height_cm']         = new HeightParam($height, $userHeight, "'height_cm' is changed");
            $this->values['auto_height']       = new AutoHeightParam($isAutoHeight, "'auto_height' is changed");
            $this->values['obj_min_height_cm'] = new XmlParam($libConfig->ObjectHeightFilter->minHeight, RangeValidator::Create(50, 200),'get_cm', 1000, "'obj_min_height_cm' is changed", 'set_cm');

            /*  HW PARAMETERS
             */
            $hwParams = $this->hwConfig->Other;
            $ir_channels = array_filter(explode(',', $hwParams->supportedModulationFrequencies['value']),function(&$v) {
                $v = floatval($v);
                return TRUE;
            });

            $this->values['supported_ir_channels']= new StaticParam($ir_channels, TRUE);
            $this->values['ir_channel']           = new XmlParam($hwParams->modulationFrequency, EnumValidator::Create($ir_channels), 'get_float', 1001, "'ir_channel' is changed");

            /* Read FoV
            */
            $this->values['fov_m']  = new FOVParam('/tmp/cv2www.fov', $isCVEnabled);
            $this->values['afov_m'] = new AFOVParam($height, $isCVEnabled);

            //only for auto fw update
            $downloadTask = get_element($this->schedulerConfig, '//*[@UUID="auto_firmware_download"]', array('JobList', 'Job'));
            $updateTask   = get_element($this->schedulerConfig, '//*[@UUID="auto_firmware_update"]', array('JobList', 'Job'));
            $isDownloadActive = $downloadTask['Active'] == 1;
            $isUpdateActive = $updateTask['Active'] == 1;
            $this->values['auto_fw_update'] = new ValueParam( $isDownloadActive && $isUpdateActive, 'bool_validator', 1000, "'auto_fw_update' is changed" );

            $this->cvConfig = $libConfig;
        }
        else
        {
            return ErrorController::internalErrorAction('3dhw READ XML');
        }
        return $this->values;
    }

    private function loadEnterArea( $enterAreaNode, $enterAreaXml )
    {
        $ea = array();
        $ea['mode'] = get_string($enterAreaNode);
        $ea['auto_thickness'] = intval($enterAreaNode['thickness']);

        $ea['auto_contours'] = array();
        if (file_exists($enterAreaXml) && $autoContoursXml = simplexml_load_file($enterAreaXml))
            $ea['auto_contours'] = get_auto_contours($autoContoursXml);

        $ea['use_shapes_in_auto'] = (string)($enterAreaNode['useShape']);
        settype($ea['use_shapes_in_auto'], 'bool');
        $ea['shapes'] = get_shapes($enterAreaNode);
        return $ea;
    }

    private function loadSteadyTrackRecovery( $steadyTrackRecovery )
    {
        $str = array();
        $str['enable'] = get_bool($steadyTrackRecovery->useSteadyTrackRecovery);
        $str['mode'] = get_string($steadyTrackRecovery->workingArea);
        $str['recovery_timeout'] = get_int($steadyTrackRecovery->recoveryTimeout);
        $str['disappeared_only'] = get_bool($steadyTrackRecovery->disappearedOnly);
        $str['areas'] = get_working_area($steadyTrackRecovery->workingArea);
        return $str;
    }

    public function check(&$isChanged)
    {
        if ($this->values['ir_channel']->getStatus($c,$m))
        {
            $isChanged -= 1;
            $this->hwConfig->asXML($this->filename);
            // Create a flag with 0 value which will signal about the change of frequency
            file_put_contents( "/data/Raptor/change_frequency_flag", "0" );
        }

        // TODO: check polygons and anchors
        if ( $this->values['roi_regions']->getStatus($c,$m) )
        {
            if ( $this->values['use_roi_regions']->getValue() == TRUE && empty($this->values['roi_regions']->getValue()))
            {
                $this->cvConfig->MaskParams->useROIPolygonArea['value'] = 0;
            }
        }

        if ( $this->values['use_roi_regions']->getStatus($c,$m) )
        {
            if ($this->values['use_roi_regions']->getValue() == TRUE)
            {
                $region = $this->values['roi_regions'];
                if (empty($region->getValue()))
                    return 'valid region is not set';
            }
        }

        if ( $this->values['enter_area']->getStatus($c,$m) )
        {
            set_enter_area($this->cvConfig->DepthObjectTracker, $this->values['enter_area']->getValue($c,$m));
            set_ea_status(self::ENTER_AREA);
        }

        if ( $this->values['steady_track_recovery']->getStatus($c,$m) )
        {
            $new = $this->values['steady_track_recovery']->getValue($c,$m);
            set_steady_track_recovery($this->cvConfig->DepthObjectTracker->trackRecovery->steadyTrackRecovery, $new);

            // [DEV-8596] when "Zones" is set as a mode, counting xml shall also update excludeNonReporting to "1", and
            // revert to "0" otherwise when either Area of FoV mode has been chosen.
            if (isset($new['mode']))
            {
                $this->countingConfig = simplexml_load_file(self::COUNTING_CONFIG);
                if ($new['mode'] == 'Zones')
                    $this->countingConfig->ZCSettings->ExcludeNonReporting['value'] = 1;
                else
                    $this->countingConfig->ZCSettings->ExcludeNonReporting['value'] = 0;
            }
        }

        self::maskCalculate($this->cvConfig->MaskParams, $mask);
        $anchors = get_anchors($this->cvConfig->AnchorPoints);
        $squareSize = get_int($this->cvConfig->AnchorPoints->anchorSquareSize);
        foreach($anchors as $pt) {
            $halfSize = intval($squareSize/2);
            for ($x = $pt[0] - $halfSize; $x < $pt[0] + $halfSize; $x++)
                for ($y = $pt[1] - $halfSize; $y < $pt[1] + $halfSize; $y++)
                    if (!$mask[$x][$y])
                        return "anchor points do not fall into the roi";
        }

        // Uncertainty check
        $auto_tilt = $this->values['auto_tilt_pan'];
        $lock = $this->values['lock_tilt_pan_height'];
        $height = $this->values['height_cm'];
        $auto_height = $this->values['auto_height'];
        if ( ($lock->getValue() == TRUE ) && ($auto_height->isUsed() == TRUE) )
            return "You can not switch on auto_height mode when lock is set";

        if ( ($lock->getValue() == TRUE ) && ($auto_tilt->isUsed() == TRUE) )
            return "You can not switch on auto_tilt_pan mode when lock is set";

        // If lock is set then ignore new anchor points
        if ( $lock->getValue() == TRUE && $this->values['floor_anchors']->getStatus($c,$m) )
            return "anchor points can not be changed because of the lock is set";

        if ( $lock->getValue() == TRUE && $this->values['tilt_deg']->getStatus($c,$m) )
            return "tilt_deg can not be changed because of the lock is set";

        if ( $lock->getValue() == TRUE && $this->values['pan_deg']->getStatus($c,$m) )
            return "pan_deg can not be changed because of the lock is set";

        if ( $lock->getValue() == TRUE && $this->values['height_cm']->getStatus($c,$m) )
            return "anchor points can not be changed because of the lock is set";

        if ($this->values['tilt_deg']->getStatus($c,$m) && ($auto_tilt->getValue() == TRUE))
            return "Tilt can not be switched while auto_tilt_pan is set";

        if ($this->values['pan_deg']->getStatus($c,$m) && ($auto_tilt->getValue() == TRUE))
            return "Pan can not be switched while auto_tilt_pan is set";

        if ($height->getStatus($c,$m))
        {
            if ($auto_height->getValue() == TRUE)
                return 'manual set the height is not possible while auto_height is on';

            $minHeight =  $this->values['obj_min_height_cm']->getValue();
            if ($height->getValue() < $minHeight )
                return "manual object height must be >= than $minHeight";
        }
        else if ($auto_height->getStatus($c, $m))
        {
            if ($auto_height->getValue() == FALSE) {
                if (is_null($height->getValue()))
                    return 'manual height is not set';
                if ($height->isUsed() !== TRUE)
                    return "height_cm must be set if auto_height is set to false";
                $height->forceSet($height->getValue());
            } else {
                $height->forceSet(0);
            }
        }

        // Lock and unlock
        if ( $lock->getStatus( $c, $m ) )
        {
            $tiltParams = $this->cvConfig->TiltCorrection;
            $userHeight = $this->cvConfig->TopViewHumanDetection->userHeight;
            if ( $lock->getValue() == TRUE ) {
                $content = "userTilt: ".$tiltParams->userTilt['value']."\n".
                           "userPan: ".$tiltParams->userPan['value']."\n".
                           "forceUserTiltPan: ".$tiltParams->forceUserTiltPan['value']."\n".
                           "userHeight: ".$userHeight['value']."\n";
                file_put_contents( "/data/Raptor/lock_tilt_pan_height_state", $content );

                $tiltParams->userTilt['value'] = $this->values['tilt_deg']->getValue();
                $tiltParams->userPan['value'] = $this->values['pan_deg']->getValue();
                $tiltParams->forceUserTiltPan['value'] = 1;
                $userHeight['value'] = floatval($this->values['height_cm']->getValue()) / 100;
            } else {
                if ( file_exists( self::LOCK_TILT_PAN_HEIGHT_STATE ) )
                {
                    $lines = @file ( self::LOCK_TILT_PAN_HEIGHT_STATE );
                    if (!empty($lines[0]))
                        $tiltParams->userTilt['value'] = floatval(substr($lines[0], 10, -1));
                    if (!empty($lines[1]))
                        $tiltParams->userPan['value'] = floatval(substr($lines[1], 9, -1));
                    if (!empty($lines[2]))
                        $tiltParams->forceUserTiltPan['value'] = intval(substr($lines[2], 18, -1));
                    if (!empty($lines[3]))
                        $userHeight['value'] = floatval(substr($lines[3], 12, -1));

                    unlink( self::LOCK_TILT_PAN_HEIGHT_STATE );
                }
            }
        }

        //only for auto fw update
        if ($this->values['auto_fw_update']->getStatus($c,$m))
        {
            $itemDownload = get_auto_update_element('download', 'fw', $this->schedulerConfig);
            $itemUpdate = get_auto_update_element('update', 'fw', $this->schedulerConfig);
            if ( isset( $itemDownload['Active'] ) && isset( $itemUpdate['Active'] ) )
                $itemDownload['Active'] = $itemUpdate['Active'] = $this->values['auto_fw_update']->getValue();

            save_xml_lock_file(self::SCHEDULER_CONFIG, $this->schedulerConfig->asXML());
        }

        // [DEV-8596]
        if (isset($this->countingConfig))
            save_xml_lock_file(self::COUNTING_CONFIG, $this->countingConfig->asXML());

        return TRUE;
    }

    private static function readLine($file, $number)
    {
        $lines = @file ($file);
        return $lines[$number];
    }

    private function maskCalculate($MaskParams, &$mask)
    {
        // create coverage mask
        $lensCoverage = get_int($this->hwConfig->ToFOptics->{get_string($this->hwConfig->ToFLensType)}->lensCoverageDiam);
        $ellipseSize = array($lensCoverage, $lensCoverage);
        $tofCenter = array(get_int($this->hwConfig->lensCenter->X), get_int($this->hwConfig->lensCenter->Y));
        $maskSize = array(get_int($this->hwConfig->DataSize->Width), get_int($this->hwConfig->DataSize->Height));
        make_ellipse_mask($tofCenter, $ellipseSize, $maskSize, $mask);

        // apply interest area ellipse
        if (get_bool($MaskParams->useInterestAreaEllipse))
        {
            $ellipseSize = array(get_int($MaskParams->ellipseWidth), get_int($MaskParams->ellipseHeight));
            $center = array(get_int($MaskParams->ellipseCenterX), get_int($MaskParams->ellipseCenterY));
            make_ellipse_mask($center, $ellipseSize, $maskSize, $interestMask);
            mask_logical_and($mask, $interestMask);
        }

        // apply roi polygon area
        if ($this->values['use_roi_regions']->getValue() == TRUE)
        {
            make_multi_polygon_mask($this->values['roi_regions']->getValue(), $maskSize, $roiMask);
            mask_logical_and($mask, $roiMask);
        }
    }
}

class CVController
{
    const HW_CONFIG = '/data/Raptor/3dhw.xml';
    const LIB_CONFIG = '/data/Raptor/3dcv.xml';

    public function cvAction()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET')
            return self::getValues();
        if ($_SERVER['REQUEST_METHOD'] == 'POST')
            return self::setValues();
    }

    private function getValues()
    {
        if (!empty($_SERVER['QUERY_STRING']))
            return ErrorController::unexpectedParameters();

        $cv = new CVValues(self::HW_CONFIG);
        $resource = new ConfigResource(self::LIB_CONFIG, array($cv,'build'));

        if ($resource->IsGood)
            return $resource->getValues();
        return ErrorController::internalErrorAction('3dcv GET XML');
    }

    private function setValues()
    {
        if (!empty($_SERVER['QUERY_STRING']))
            return ErrorController::unexpectedParameters();

        $cv = new CVValues(self::HW_CONFIG);
        $resource = new ConfigResource(self::LIB_CONFIG, array($cv,'build'), array($cv,'check'));
        if ($resource->IsGood)
        {
            $ret = $resource->setValues();
            // DEV-8366 a workaround until we make asynchronous mode
            if (stripos($ret, "'enter_area' is changed") !== false)
                header($_SERVER['SERVER_PROTOCOL']." 202 Accepted");
            return $ret;
        }
        return ErrorController::internalErrorAction('3dcv POST XML');
    }
}
?>
