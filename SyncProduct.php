<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/4
 * Time: 16:39
 */

class AskPublicSystemService_SyncProduct extends Common_AskPublicSystemService
{
    /**
     * 同步商品实收数据
     * @param $customerCode
     * @param $subscribeTypeCode
     * @return bool 返回 false || messageChannelKey(string)
     */
    public function syncProduct($data)
    {
        if (empty($data)) {
            return $result = [
                'ask' => Common_AskPublicSystemService::SUCCESS,
                'errMessage' => ''
            ];
        }

        $result = [
            'ask' => Common_AskPublicSystemService::FAIL,
            'errMessage' => ''
        ];

        $product_package_type_re = [
            'package' => 0,
            'letter' => 1,
        ];

        foreach ($data as $product_id => $datum) {
            //公共系统为Int类型处理
            if (isset($data[$product_id]['product_package_type'])) {
                switch ($data[$product_id]['product_package_type']) {
                    case 'package' :
                    case 'letter' :
                        $product_package_type = $product_package_type_re[trim($data[$product_id]['product_package_type'])];
                        break;
                    default :
                        throw new Exception('product_package_type 同步时必填并不能为空只能为package/letter');
                }
                $data[$product_id]['product_package_type'] = $product_package_type;
            }
            //因历史原因product_receive_status需改为1;
            if (isset($data[$product_id]['product_receive_status'])
                && $data[$product_id]['product_receive_status'] == 2
            ) {
                $data[$product_id]['product_receive_status'] = 1;
            }
        }
        $mappingRelationArrFormat = [
            'ProductId' => 'product_id',
            'ReceiveLength' => 'product_real_length',
            'ReceiveWidth' => 'product_real_width',
            'ReceiveHeight' => 'product_real_height',
            'ReceiveWeight' => 'product_real_weight',
            'ReceiveStatus' => 'product_receive_status',
            'ProductPackageType' => 'product_package_type',
        ];
        $this->apiCode = '0030002';
        $this->rightReturnFormat = [];
        try {
            foreach ($data as $key => $datum) {
                foreach ($mappingRelationArrFormat as $relationKey => $key) {
                    if (!array_key_exists($key, $datum) || $datum[$key] === '') {
                        throw new Exception($key . ' 同步时必填并不能为空');
                    }
                }
            }
            $sendData = self::mappingRelationArrFormat($mappingRelationArrFormat, $data);
            $sendData = ['ProductList' => array_values($sendData)];

            $sendPost = $this->sendPost($this->apiCode, $sendData);

            $result[] = $sendData;
            $result[] = $sendPost;
            $validatorResponseParams = $this->validatorResponseParams($sendPost);
            if ($validatorResponseParams['ask'] == Common_AskPublicSystemService::SUCCESS) {
                $result = [
                    'ask' => Common_AskPublicSystemService::SUCCESS,
                    'errMessage' => '同步成功'
                ];
            } else {
                throw new Exception($validatorResponseParams['errMessage']);
            }
        } catch (Exception $e) {
            $result['errMessage'] = $e->getMessage();
        }
        return $result;
    }
}
