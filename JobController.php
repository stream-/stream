<?php

namespace app\controllers;

use app\core\controllers\CoreController;
use app\models\CareerExtra;
use app\models\Cities;
use app\models\CountriesExtra;
use app\models\JobExtra;
use app\models\JobDescriptionModel;
use app\models\JobStatus;
use app\models\JobTypeModel;
use app\models\SalesRegionsExtra;

use Yii;
use yii\data\Pagination;
use yii\web\Response;
use yii\widgets\LinkPager;
use yii\helpers\Url;
use app\models\LanguageExtra;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\web\HttpException;

use yii\filters\AccessControl;
use yii\filters\VerbFilter;

class JobController extends CoreController
{
    public $breadcrumps = [];
    public $layout = 'admin';
    private $pagination;
    
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => [
                            'index',
                            'profile',
                            'set-job-status'
                        ],
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'actions' => [
                            'index',
                            'create',
                            'edit',
                            'delete'
                        ],
                        'roles' => ['admin']
                    ]
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }
    
    public function init(){
        parent::init();
        $this->breadcrumps[] = [
            'Jobs'  =>  Url::toRoute(['/job/index'])
        ];

        $this->pagination = new Pagination(['pageSize' => 5]);
    }

    /**
     * Method for getting list of all jobs
     *
     * @throws
     * @return string
     */
    public function actionIndex()
    {
        $countries = CountriesExtra::find()->select(['code', 'en'])->asArray()->all();
        $aLanguages = LanguageExtra::getAllActiveLanguages();
        $aJobStatuses = JobStatus::find()->asArray()->all();
        $aSalesRegions = SalesRegionsExtra::find()
            ->asArray()
            ->all();
        $dataProvider = null;
        $getData = !empty(Yii::$app->request->post()) ? Yii::$app->request->post() : Yii::$app->request->get();
        if (!empty($getData)){
            $jobsQuery = JobExtra::searchJobs($getData);
            $dataProvider = new ActiveDataProvider([
                'query' => $jobsQuery,//->asArray()->all(),
                'pagination' => [
                    'pagesize' => 8,
                ],
            ]);
        }

        return $this->render('list', [
            'countries'  => $countries,
            //'pager'      => $pager,
            'aLanguages' => $aLanguages,
            'dataProvider'  => $dataProvider,
            'aJobStatuses'  => $aJobStatuses,
            'aSalesRegions' => $aSalesRegions
        ]);
    }

    /**
     * Method for getting job profile by ID
     *
     * @param $id
     * @throws
     * @return string
     */
    public function actionProfile($id=null)
    {
        if (empty($id)) {
            throw new HttpException(404, 'No results found');
        }
        $this->breadcrumps[] = [
            'Profile'  =>  Url::toRoute(['/job/profile'])
        ];

        $mJob = JobExtra::getJob($id);
        $oJobModel = JobExtra::findOne(['id' => $id]);
        $aJobStatuses = JobStatus::find()->asArray()->all();
        if (empty($oJobModel)) {
            throw new HttpException(404, 'No results found');
        }
        
        $jobCity = $mJob['city'];
        $appliedJob = CareerExtra::getAppliedJobs($id);

        for ($i = 0; $i < count($appliedJob); $i++) {
            if (!empty($appliedJob[$i]['city'])) {
                $appliedJob[$i]['city_name'] = Cities::findOne(['id' => $appliedJob[$i]['city']])->name;
            }
        }
        
        return $this->render('profile', [
            //'jobProfile' => $jobProfile,
            'mJob'        => $mJob,
            'jobCity'    => (!empty($jobCity)) ? $jobCity : '',
            'appliedJob' => $appliedJob,
            'aJobStatuses' => $aJobStatuses,
            'oJobModel' => $oJobModel
        ]);
    } 

    /**
     * Method for creating new job
     *
     * @throws
     * @return string | array
     */
    public function actionCreate()
    {
        $languages = LanguageExtra::getAllActiveLanguages();
        $this->breadcrumps[] = [
            'Create Job'  =>  Url::toRoute(['/job/create'])
        ];

        $oJob = new JobExtra();
        $oJobDescription = new JobDescriptionModel();
        $countries = CountriesExtra::find()->select(['code', 'en'])->asArray()->all();
        $oRequest = Yii::$app->request;
        $aJobTypes = JobTypeModel::getAllTypes();
        $aSalesRegions = SalesRegionsExtra::getAllSalesRegions();
        if ($oRequest->isPost && $oRequest->isAjax) {
            if (!empty($oRequest->post())) {
                $data = Yii::$app->request->post();
                Yii::$app->response->format = Response::FORMAT_JSON;

                return JobExtra::createNewJob($data);
            }
        }
        return $this->render('create', [
            'oJob'             => $oJob,
            'oJobDescription'  => $oJobDescription,
            'languages'       => $languages,
            'countries'       => $countries,
            'aJobTypes' => $aJobTypes,
            'aSalesRegions' => $aSalesRegions
        ]);
    }
    /**
     * Method for editing job by ID
     *
     * @param $id
     * @throws
     * @return string | array
     */
    public function actionEdit($id = null)
    {
        $this->breadcrumps[] = [
            'Edit'  =>  Url::toRoute(['/job/edit'])
        ];
        
        $oRequest = Yii::$app->request;
        
        if (empty($id)) {
            throw new HttpException(404, $id);
        }
        $oJob = JobExtra::findOne(['id' => $id]);
        $oJobDescription = JobDescriptionModel::getJobDescriptionById($id);
        $aJobTypes = JobTypeModel::getAllTypes();
        $aSalesRegions = SalesRegionsExtra::getAllSalesRegions();
        if (empty($oJobDescription) || empty($oJob)) {
            throw new HttpException(404, $id);
        }
        
        $languages = LanguageExtra::getAllActiveLanguages();
        
        if ($oRequest->isPost && $oRequest->isAjax) {
            if (!empty($oRequest->post())) {
                $data = Yii::$app->request->post();
                Yii::$app->response->format = Response::FORMAT_JSON;
                return JobExtra::updateJob($oJob->id, $data);
            }
        }

        return $this->render('create', [
            'oJob'             => $oJob,
            'oJobDescription'  => $oJobDescription,
            'languages'       => $languages,
            'aJobTypes'    => $aJobTypes,
            'aSalesRegions' => $aSalesRegions
        ]);
    }

    /**
     * Method for deleting job by ID
     *
     * @param $id
     * @throws
     * @return string | array
     */
    public function actionDelete($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (JobExtra::deleteJob($id)) {
            $json = [
                'status'  => 'success',
                'message' => 'Job successfully deleted!'
            ];
        } else {
            $json = [
                'status'  => 'fail',
                'message' => 'Job is not deleted!'
            ];
        }

        return $json;
    }
    
    public function actionSetJobStatus($id = null)
    {
        $oRequest = Yii::$app->request;
        if ($oRequest->isPost && $oRequest->isAjax) {
            if (!empty($id)) {
                $oJob = JobExtra::findOne(['id' => $id]);
                 if (!empty($oJob)) {
                    if (!empty($oRequest->post()['status'])) {
                        $oJob->status = $oRequest->post()['status'];
                        $oJob->published_at = time();
                        if ($oJob->validate()) {
                            $oJob->save();
                            return $this->asJson([
                                'success' => true,
                                'model' => $oJob->formName()
                            ]);
                        } else {
                            return $this->asJson([
                                'validations' => $oJob->getErrors(),
                                'model' => $oJob->formName()
                            ]);
                        }
                    }
                    
                }
            }
        }
    }
}
