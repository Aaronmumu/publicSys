<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/6
 * Time: 17:31
 */

class ForPublicSystemService_User extends Common_ForPublicSystemService
{
    public function __construct()
    {
        parent::__construct();
        $this->_table = 'user';
    }

    /**
     * 预处理验证
     * @param $type
     * @param $item
     * @throws Exception
     */
    public function preValidatorParams($type, $item) {
        if (empty($item['base_data']['user_id'])) {
            throw new Exception(Common_ForPublicSystemService::PARAM_ERR);
        }
        if (strtolower($type) == strtolower('sync')) {
            $operationIdentity  = $item['sync_header']['operation_identity'];
            switch ($operationIdentity) {
                case Common_ForPublicSystemService::ACTION_ADD :
                    $this->isExistData($item['base_data']['user_id'], $operationIdentity);
                    break;
                case Common_ForPublicSystemService::ACTION_UPDATE :
                    $this->isExistData($item['base_data']['user_id'], $operationIdentity);
                    break;
                case Common_ForPublicSystemService::ACTION_DELETE :
                    $this->isExistData($item['base_data']['user_id'], $operationIdentity);
                    break;
            }
        }
    }

    /**
     * 失败操作
     * @param $id
     * @throws Exception
     */
    public function isExistData($id, $operationIdentity)
    {
        $data = Service_User::getByField($id);
        if (empty($data)) {
            switch ($operationIdentity) {
                case Common_ForPublicSystemService::ACTION_ADD :
                    break;
                case Common_ForPublicSystemService::ACTION_UPDATE :
                    throw new Exception(Common_ForPublicSystemService::FAIL);
                    break;
                case Common_ForPublicSystemService::ACTION_DELETE :
                    throw new Exception(Common_ForPublicSystemService::FAIL);
                    break;
            }
        } else {
            switch ($operationIdentity) {
                case Common_ForPublicSystemService::ACTION_ADD :
                    throw new Exception(Common_ForPublicSystemService::Synchronization_Expired);
                    break;
                case Common_ForPublicSystemService::ACTION_UPDATE :
                    break;
                case Common_ForPublicSystemService::ACTION_DELETE :
                    break;
            }
        }
    }

    /**
     * 用户 对接公共系统-增删改
     * @param $params
     * @return array
     */
    public function createUser($params)
    {
        $data = [];
        $sfps_api_name = 'createUser';

        foreach ($params as $item) {
            $itemRes = self::getSyncResultFormat($item);
            try {
                //检验数据
                $this->validatorParams('sync', $item, $this->_table, 'user_id', '');
                $this->preValidatorParams('sync', $item);
                $res = (new Process_User())->handleUserFromPublishSystem($item['base_data'], $itemRes['operation_identity']);
                $itemRes['message'] = $res['message'];
                $itemRes['sfps_table_id'] = $res['sfps_table_id'];
                $itemRes['sfps_table_data'] = $res['sfps_table_data'];
            } catch (Exception $e) {
                $itemRes['message'] = $e->getMessage();
            }
            $data[] = self::formatReturnSyncErrMess($sfps_api_name, $itemRes, $this->_table);
        }
        //回应
        return $this->response($data);
    }
}
