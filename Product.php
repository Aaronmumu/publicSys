<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/5
 * Time: 11:21
 */

class ForPublicSystemService_Product extends Common_ForPublicSystemService
{
    public function __construct()
    {
        parent::__construct();
        $this->_table = 'product';
    }

    /**
     * 预处理验证
     * @param $type
     * @param $item
     * @throws Exception
     */
    public function preValidatorParams($type, $item)
    {
        if (empty($item['base_data']['product_id'])) {
            throw new Exception(Common_ForPublicSystemService::PARAM_ERR);
        }
        if (strtolower($type) == strtolower('sync')) {
            $operationIdentity = $item['sync_header']['operation_identity'];
            switch ($operationIdentity) {
                case Common_ForPublicSystemService::ACTION_ADD :
                    $this->isExistData($item['base_data']['product_id'], $operationIdentity);
                    break;
                case Common_ForPublicSystemService::ACTION_UPDATE :
                    $this->isExistData($item['base_data']['product_id'], $operationIdentity);
                    break;
                case Common_ForPublicSystemService::ACTION_DELETE :
                    $this->isExistData($item['base_data']['product_id'], $operationIdentity);
                    break;
            }
        }
    }

    /**
     * 失败操作
     * @param $id
     * @throws Exception
     */
    public function isExistData($productId, $operationIdentity)
    {
        $data = Service_Product::getByField($productId);
        if (empty($data)) {
            switch ($operationIdentity) {
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
            }
        }
    }

    /**
     * 商品 对接公共系统-增删改
     * @param $params
     * @return array
     */
    public function createProduct($params)
    {
        $sfps_api_name = 'createProduct';

        foreach ($params as $item) {
            $itemRes = self::getSyncResultFormat($item);
            try {
                //检验数据
                $this->validatorParams('sync', $item, $this->_table, 'product_id', '');
                $this->preValidatorParams('sync', $item);
                $res = (new Process_Product())->handleProductFromPublishSystem($item['base_data'], $itemRes['operation_identity']);
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

    /**
     * 获取商品详情-库存查询
     * @param $product_id
     * @return array
     */
    public function getInventory($params)
    {
        if (empty($params['product_id'])) {
            throw new Exception('参数product_id必填');
        }
        $data = [];
        $params = $this->validatorParamspagination($params);
        $product_id = $params['product_id'];
        $whs = Common_DataCache::getWarehouse();
        $productInventory = new Service_ProductInventory();

        $params['total'] = $productInventory->getByCondition(array('product_id' => $product_id), 'count(*)');

        if ($params['total'] > 0) {
            $data = $productInventory->getByCondition(array('product_id' => $product_id), '*', $params['pageSize'], $params['page']);
            foreach ($data as $key => $value) {
                $w = $whs[$value['warehouse_id']];
                $data[$key]['warehouse_code'] = $w['warehouse_code'] . "[{$w['warehouse_desc']}]";
                $data[$key]['pi_sellable'] = $value['pi_sellable'] + $value['pi_reserved'];
            }
        }
        //回应
        return $this->responseGet($params, $data);
    }

    /**
     * 获取商品详情-订单
     * @param $product_id
     * @return array
     */
    public function getOrder($params)
    {
        if (empty($params['product_id'])) {
            throw new Exception('参数product_id必填');
        }
        $data = [];
        $params = $this->validatorParamspagination($params);
        $product_id = $params['product_id'];
        $whs = Common_DataCache::getWarehouse();
        $opService = new Service_OrderProduct();

        $params['total'] = $opService->getByCondition(array('product_id' => $product_id), 'count(*)');

        if ($params['total'] > 0) {
            $data = $opService->getByCondition(array('product_id' => $product_id), '*', $params['pageSize'], $params['page'], array('order_id desc'));
            foreach ($data as $k => $v) {
                $order = Service_Orders::getByField($v['order_id'], 'order_id', array('warehouse_id', 'order_status'));
                $v['order_status'] = $order['order_status'];
                $w = $whs[$order['warehouse_id']];
                $v['warehouse_code'] = $w['warehouse_code'] . "[{$w['warehouse_desc']}]";
                $data[$k] = $v;
            }
        }
        //回应
        return $this->responseGet($params, $data);
    }

    /**
     * 获取商品详情-入库单
     * @param $product_id
     * @return array
     */
    public function getAsn($params)
    {
        if (empty($params['product_id'])) {
            throw new Exception('参数product_id必填');
        }
        $data = [];
        $params = $this->validatorParamspagination($params);
        $product = Service_Product::getByField($params['product_id']);
        if (empty($product)) {
            throw new Exception("商品不存在");
        }

        $product_barcode = $product['product_barcode'];
        $gcReceivingObj = new Service_GcReceiving();
        $params['total'] = $gcReceivingObj->getLeftJoinOnlyReceivingByCondition(array('product_barcode' => $product_barcode), 'count(*)');
        if ($params['total'] > 0) {
            $data = $gcReceivingObj->getLeftJoinOnlyReceivingByCondition(array('product_barcode' => $product_barcode),['receiving_code', 'receiving_id', 'warehouse_code'], $params['pageSize'], $params['page'], array('receiving_id desc'));
        }
        //回应
        return $this->responseGet($params, $data);
    }

    /**
     * 获取商品详情-批次库存
     * @param $product_id
     * @return array
     */
    public function getInventoryBatch($params)
    {
        if (empty($params['product_id'])) {
            throw new Exception('参数product_id必填');
        }
        $data = [];
        $params = $this->validatorParamspagination($params);
        $product_id = $params['product_id'];
        $inventoryBatchService = new Service_InventoryBatch();

        $params['total'] = $inventoryBatchService->getByCondition(array('product_id' => $product_id), 'count(*)');

        if ($params['total'] > 0) {
            $data = $inventoryBatchService->getByCondition(array('product_id' => $product_id), '*', $params['pageSize'], $params['page']);
        }
        //回应
        return $this->responseGet($params, $data);
    }

    /**
     * 获取商品详情-相关退件单
     * @param $product_id
     * @return array
     */
    public function getReturnAsn($params)
    {
        if (empty($params['product_id'])) {
            throw new Exception('参数product_id必填');
        }
        $data = [];
        $params = $this->validatorParamspagination($params);
        $product_id = $params['product_id'];

        $afterSalesReturnOrderProduct = new Service_AfterSalesReturnOrderProduct();
        $params['total'] = $afterSalesReturnOrderProduct->getReturnOrderByProduct(array('product_id' => $product_id), 'count(*)');

        if ($params['total'] > 0) {
            $data = $afterSalesReturnOrderProduct->getReturnOrderByProduct(array('product_id' => $product_id), '*', $params['pageSize'], $params['page']);
        }
        //回应
        return $this->responseGet($params, $data);
    }
}
