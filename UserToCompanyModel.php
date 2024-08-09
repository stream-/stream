<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "user_to_company".
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $company_id
 *
 * @property CompanyProfile $company
 * @property User $user
 */
class UserToCompanyModel extends UserToCompany
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_to_company';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [['user_id', 'company_id'], 'integer'],
            [['company_id'], 'exist', 'skipOnError' => true, 'targetClass' => CompanyProfile::className(), 'targetAttribute' => ['company_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'company_id' => 'Company ID',
        ];
    }

    /**
     * Gets query for [[Company]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCompany()
    {
        return $this->hasOne(CompanyProfile::className(), ['id' => 'company_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
    
    /**
     * Method of getting all users of company
     *
     * @param $id
     * @return array
     */
    public static function getAllCompanyUsers($id)
    {
        $usersIds = self::find()->select('user_id')->where(['company_id' => $id])->asArray()->all();

        $users = RegistrationExtra::find()->where(['user_id' => $usersIds])->asArray()->all();

        return ($users) ? $users : [];
    }

    /**
     * Method of deleting user from company
     *
     * @param $companyId
     * @param  $userId
     * @throws
     * @return boolean
     */
    public static function deleteUserAuth($companyId, $userId)
    {
        $user = self::findOne(['company_id' => $companyId, 'user_id' => $userId]);

        return $user->delete();
    }

    /**
     * Method of creating user in company
     *
     * @param $userId
     * @param $companyId
     * @return boolean
     */
    public static function createUserAuth($companyId, $userId)
    {
        $user = new self;
        $user->user_id = $userId;
        $user->company_id = $companyId;

        return $user->save(false);
    }
    
}
