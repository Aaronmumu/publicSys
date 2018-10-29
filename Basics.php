<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/4
 * Time: 16:39
 */

class ForPublicSystemService_Basics extends Common_ForPublicSystemService
{
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * 获取运输方式
     */
    public function getShippingMethodList($params = array())
    {
        $return = array(
            'ask' => 'Failure',
            'message'   =>  '',
            'data' => array()
        );
        try{
            $con['sm_status']=1;//表示可用
            $warehouseCode = empty($params['warehouseCode']) ? '' : $params['warehouseCode'];
            if(isset($params['smCodeType']) && $params['smCodeType']!=''){
                $con['sm_code_type']=$params['smCodeType'];
            }
            if (!empty($warehouseCode)) {
                $warehouseRow = ServiceRead_Warehouse::getByField($warehouseCode, 'warehouse_code');
                if(!empty($warehouseRow['warehouse_id'])){
                    $con['warehouse_id']=$warehouseRow['warehouse_id'];
                }
            }
            $showField=array(
                'shipping_method.sm_code as code',
                'shipping_method.sm_name_cn as name',
                'shipping_method.sm_name as name_en',
                'shipping_method.sm_code_type as type',
                'shipping_method.sm_is_signature as sm_is_signature',
                'warehouse.warehouse_code',
                'sp.sp_code'

            );
            $shippingModl=new Table_ShippingMethod();
            $data=$shippingModl->getBymethodSettingServiceProvider($con,$showField);
            $return['ask']  = 'Success';
            $return['data'] = $data;
        }catch (Exception $e){
            $return['message'] = $e->getMessage();
        }
        return $return;
    }

    /**
     * @desc 获取仓库列表
     * @return array
     */
    public function getWarehouse($params)
    {
        $return = array(
            'ask' => 'Failure',
            'data' => array(),
            'message'=>''
        );
        try {
            $pageSize = empty($params['pageSize']) ? 0 : $params['pageSize'];
            $page = empty($params['page']) ? 0 : $params['page'];
            $con = array('warehouse_status' => 1);
            $return['pagination'] = array(
                'page' => $page,
                'pageSize' => $pageSize
            );
            if(isset($params['warehouseType']) && $params['warehouseType']!==''){
                $con['warehouse_type']=$params['warehouseType'];
            }
            $warehouse = new Table_Warehouse();
            $count = $warehouse->getByCondition($con,'count(*)');
            $return['count'] = $count;
            $return['nextPage'] = $pageSize * $page && $pageSize * $page < $count ? 'true' : 'false';
            $field = array(
                'warehouse_code',
                'country_code',
                'warehouse_desc as warehouse_name',
                'warehouse_id',
                'warehouse_type'
            );
            $country = $warehouse->getByCondition($con,$field,$pageSize, $page);
            $return['data'] = $country;
            $return['ask'] = 'Success';
            $return['message'] = 'Success';
        } catch (Exception $e) {
            if($e->getCode()==42){
                $return['ask'] = 'Failure';
                $return['message'] = Ec::Lang('system error');
                $return['Error'] = array(
                    'errMessage' => Ec::Lang('system error'),
                    'errCode' => Enum_ErrorCode::commonSystemError
                );
                Ec::showError($e->getFile().$e->getLine().$e->getMessage(),'getRegionForReceiving'.date('Y-m-d'));
            }else{
                $return['ask'] = 'Failure';
                $return['message'] = $e->getMessage();
                $return['Error'] = array(
                    'errMessage' => $e->getMessage(),
                    'errCode' => $e->getCode()
                );
            }
        }
        return $return;
    }

    /*获取金融客户代码*/
    public function GetFinancialCustomerCode($params){
        $return = array(
            'ask' => 'Failure',
            'data' => array(),
            'message'=>''
        );
        try {
            $pageSize = empty($params['pageSize']) ? 0 : $params['pageSize'];
            $page = empty($params['page']) ? 0 : $params['page'];
            $con = array('warehouse_status' => 1);
            $return['pagination'] = array(
                'page' => $page,
                'pageSize' => $pageSize
            );
            if(isset($params['warehouseType']) && $params['warehouseType']!==''){
                $con['warehouse_type']=$params['warehouseType'];
            }
            $FinanceCustomer = new Table_FinanceCustomer();
            $count = $FinanceCustomer->getFinanceCustomer(array(),'count(*)');
            $return['count'] = $count;
            $return['nextPage'] = $pageSize * $page && $pageSize * $page < $count ? 'true' : 'false';
            $data = $FinanceCustomer->getFinanceCustomer(array(),'financial_customer_code',$pageSize, $page);
            $return['data'] = $data;
            $return['ask'] = 'Success';
            $return['message'] = 'Success';
        } catch (Exception $e) {
            $return['message'] = $e->getMessage();
        }
        return $return;
    }

    //验证外部商品编码是否已使用
    public function IsUseExternalSKU($params){
        $return = array(
            'ask' => 'Failure',
            'data' =>array(),
            'message'=>''
        );
        try {

            $con['product_barcode']=$params['ExternalSKU'];
            if(isset($params['ExternalSKUs']) && !empty($params['ExternalSKUs'] && is_array($params['ExternalSKUs']))){
                if(count($params['ExternalSKUs'])>100){
                    throw new Exception('数组最大数量100个');
                }
                $con['product_barcode_in']=$params['ExternalSKUs'];
            }
            if(empty($con['product_barcode']) && empty($con['product_barcode_in'])){
                throw new Exception('参数必填');
            }
            //新的入库单
            $GcReceiving =new Table_GcReceivingDetail();
            $data = $GcReceiving->getByCondition($con,array('product_barcode'));
            if(!empty($data)){
                //存在
                $skus=array_column($data,'product_barcode');
                $return['data']=array_unique($skus);
            }else{
                //旧入库单
                $ReceivingDetail = new Table_ReceivingDetail();
                $data = $ReceivingDetail->getByCondition($con,array('product_barcode'));
                if(!empty($data)){
                    $skus=array_column($data,'product_barcode');
                    $return['data']=array_unique($skus);
                }
            }
            $return['ask'] = 'Success';
            $return['message'] = 'Success';
        } catch (Exception $e) {
            $return['message'] = $e->getMessage();
        }
        return $return;
    }


}

