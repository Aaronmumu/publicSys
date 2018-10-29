<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/6
 * Time: 17:31
 */

class ForPublicSystemService_CustomerShippingMethodItem extends Common_ForPublicSystemService
{
    public function __construct()
    {
        parent::__construct();
        $this->_table = 'customer_shipping_method_item';
    }

    /**
     * 预处理验证
     * @param $type
     * @param $item
     * @throws Exception
     */
    public function preValidatorParams($type, $item)
    {
        if (empty($item['base_data']['csmi_id'])) {
            throw new Exception(Common_ForPublicSystemService::PARAM_ERR);
        }
        if (strtolower($type) == strtolower('sync')) {
            $operationIdentity = $item['sync_header']['operation_identity'];
            switch ($operationIdentity) {
                case Common_ForPublicSystemService::ACTION_ADD :
                    $this->isExistData($item['base_data']['csmi_id'], $operationIdentity);
                    break;
                case Common_ForPublicSystemService::ACTION_UPDATE :
                    $this->isExistData($item['base_data']['csmi_id'], $operationIdentity);
                    break;
                case Common_ForPublicSystemService::ACTION_DELETE :
                    $this->isExistData($item['base_data']['csmi_id'], $operationIdentity);
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
        $data = Service_CustomerShippingMethodItem::getByField($id);
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
    public function createCustomerShippingMethodItem($params)
    {
        $data = [];
        $sfps_api_name = 'createCustomerShippingMethodItem';

        $syncCustomerCode = [];

        foreach ($params as $item) {
            $itemRes = self::getSyncResultFormat($item);
            try {
                //检验数据
                $this->validatorParams('sync', $item, $this->_table, 'csmi_id', '');
                $this->preValidatorParams('sync', $item);
                $res = (new Process_CustomerShippingMethodItem())->handleCustomerShippingMethodItemFromPublishSystem($item['base_data'], $itemRes['operation_identity']);
                if ($res['message'] === Common_ForPublicSystemService::SUCCESS) {
                    $syncCustomerCode[] = $res['customer_code'];
                    unset($res['customer_code']);
                }
                $itemRes['message'] = $res['message'];
                $itemRes['sfps_table_id'] = $res['sfps_table_id'];
                $itemRes['sfps_table_data'] = $res['sfps_table_data'];
            } catch (Exception $e) {
                $itemRes['message'] = $e->getMessage();
            }
            $data[] = self::formatReturnSyncErrMess($sfps_api_name, $itemRes, $this->_table);
        }

        $syncCustomerCode = array_flip (array_flip($syncCustomerCode));
        if (!empty($syncCustomerCode)) {
            $noticeData = [];
            foreach ($syncCustomerCode as $value) {
                $noticeData[$value] = Service_CustomerShippingMethodItem::getByCondition(['customer_code' => $value], "*", 0);
            }

            //改动同步到oms
            $notice = array(
                'app_code' => 'customer_shipping_methods',
                'refer_no' => 'createCustomerShippingMethodItem',
                'action' => '',
                'data' => $noticeData,
                'customerCodeArr' => $syncCustomerCode
            );

            $process = new Common_CallOms();
            $syncResult = $process->syncNotice($notice, 1);
            if ($syncResult['ask'] == "failure") {
                unset($notice['data']);
                //实时同步失败开始走异步同步
                $syncResult = $process->syncNotice($notice, 0);
                if ($syncResult['ask'] == "failure") {
                    Ec::showError('公共系统同步客户物流产品失败', 'customer_shipping_methods_err');
                    Ec::showError(json_encode($notice), 'customer_shipping_methods_err');
                }
            }
        }

        //回应
        return $this->response($data);
    }
}
