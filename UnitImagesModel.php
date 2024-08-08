<?php

namespace app\models;

use Yii;
use yii\web\UploadedFile;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\HttpException;
/**
 * This is the model class for table "unit_images".
 *
 * @property int $id
 * @property int $unit_id
 * @property string $image_path
 * @property string $image_title
 * @property string $image_description
 *
 * @property DevelopmentUnits $unit
 */
class UnitImagesModel extends UnitImages
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return parent::tableName();
    }
    public $unitImages;
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['unit_id'], 'required'],
            [['unit_id'], 'integer'],
            [['image_path'], 'string', 'max' => 150],
            [['unit_id'], 'exist', 'skipOnError' => true, 'targetClass' => DevelopmentUnits::className(), 'targetAttribute' => ['unit_id' => 'unit_id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'unit_id' => 'Unit ID',
            'image_path' => 'Image Path',
            'image_title' => 'Image Title',
            'image_description' => 'Image Description',
        ];
    }
    
    
    public function uploadUnitImages($index, $file)
    {
        $this->unit_id = Yii::$app->session->get('unit_id');
        //if ($this->validate()) {
        $dir = dirname(__DIR__) . '/web/uploads/data/unit_images/' . $this->unit_id . '/';
        $url = 'uploads/data/unit_images/' . $this->unit_id . '/';

        if (!file_exists($dir)) {
            FileHelper::createDirectory($dir,0775,true);
        }
        $iMaxSize = 1024 * 1024 * 20;
        
        if (count($this->unitImages) > 20) {
            $this->addError('unitImages', 'You can upload at most 20 images');
            return $this;
        }
        if (!empty($file)) {
        //foreach ($this->unitImages as $key => $file) {
            $oImgModel = new $this();
            $filename = Yii::$app->security->generateRandomString(Yii::$app->params['upload_config']['length_name_file']).'.'.$file->extension;
            if ($file->size > $iMaxSize) {
                $oImgModel->addError('unitImages', 'File you are trying to upload is too big, limit is 20mb');
                 return $oImgModel;
            }
            if (!in_array($file->type, ['image/jpg', 'image/jpeg', 'image/png'])) {
                $oImgModel->addError('unitImages', 'Only files with these extensions are allowed: jpg, jpeg, png.');
                return $oImgModel;
            }
            $file->saveAs($dir.$filename);
            $oImgModel->unitImages = null;
            $oImgModel->unit_id = Yii::$app->session->get('unit_id');
            $oImgModel->img_order = $index;
            $oImgModel->image_path = $url.$filename;

            $oImgModel->save();
            
            return $oImgModel;
        //}
        }
        return new $this();//true;
    }
    
    /**
     * Maximum file size validator.
     * @param UploadedFile $file File to check.
     * @param integer $maxFileSize Maximum file size in bytes.
     * @return boolean
     */
    private function validateFileSize(UploadedFile $file, $maxFileSize = 1024 * 1024 * 20)
    {
        if ($file->error == UPLOAD_ERR_INI_SIZE)
            return false;
        if ($file->error == UPLOAD_ERR_FORM_SIZE)
            return false;
        return $file->size < $maxFileSize;
    }
    
    public static function validateImageType(/*UploadedFile*/ $file)
    {
        return in_array($file->type, ['image/jpg', 'image/jpeg', 'image/png']);
    }
    
    public static function getUnitImages(int $iUnitId = null) {
        if (!empty($iUnitId)) {
            return self::find()
                ->select(['img_order', 'ipage_path'])
                ->where(['unit_id' => $iUnitId])
                ->asArray()
                ->all();
        }
        return [];
    }
}
