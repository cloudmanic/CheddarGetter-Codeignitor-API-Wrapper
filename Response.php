<?php

/**
 * @category CheddarGetter
 * @package CheddarGetter
 * @author Marc Guyer <marc@cheddargetter.com>
 */
/**
 * Response object
 * @category CheddarGetter
 * @package CheddarGetter
 * @author Marc Guyer <marc@cheddargetter.com>
 */
 
class CheddarGetter_Response extends DOMDocument {
	
	private $_responseType;
	private $_array;
	
	/**
	 * Constructor
	 *
	 * @param $response string well formed xml
	 * @throws CheddarGetter_Response_Exception in the event the xml is not well formed
	 */
	public function __construct($response) {
		$this->_array = null;
		parent::__construct('1.0', 'UTF-8');
		
		if (!@$this->loadXML($response)) {
			throw new CheddarGetter_Response_Exception("Response failed to load into the DOM.\n\n$response", CheddarGetter_Response_Exception::UNKNOWN);
		}
		
		if ($this->documentElement->nodeName == 'error') {
			$this->_responseType = 'error';
			$this->handleError();
		}
		$this->_responseType = $this->documentElement->nodeName;
	}
	
	/**
	 * Get the response type for this request object -- usually corresponds to the root node name
	 *
	 * @return string
	 */
	public function getResponseType() {
		return $this->_responseType;
	}
	
	/**
	 * Determine whether or not the response doc contains embedded errors
	 *
	 * @return boolean
	 */
	public function hasEmbeddedErrors() {
		$arr = $this->toArray();
		return isset($arr['errors']);
	}
	
	/**
	 * Get embedded errors if any
	 *
	 * @return array|false
	 */
	public function getEmbeddedErrors() {
		if ($this->hasEmbeddedErrors()) {
			$arr = $this->toArray();
			return $arr['errors'];
		}
		return false;
	}
	
	/**
	 * Get a nested array representation of the response doc
	 *
	 * @return array
	 */
	public function toArray() {
		if ($this->_array) {
			return $this->_array;
		}
		$root = $this->documentElement;
		if ($root == 'error') {
			return array(
				0 => array(
					'id' => $root->getAttribute('id'),
					'code' => $root->getAttribute('code'),
					'auxCode' => $root->getAttribute('auxCode'),
					'message' => $root->firstChild->nodeValue
				)
			);
		}
		$this->_array = $this->_toArray($root->childNodes);
		return $this->_array;
		
	}
	
	/**
	 * Get a JSON encoded string representation of the response doc
	 *
	 * @return string
	 */
	public function toJson() {
		return json_encode($this->toArray());
	}
	
	/**
	 * Recursive method to traverse the dom and produce an array
	 *
	 * @return array
	 */
	protected function _toArray(DOMNodeList $nodes) {
		$array = array();
		foreach ($nodes as $node) {
			if ($node->nodeType != XML_ELEMENT_NODE) {
				continue;
			}
			if ($node->hasAttributes()) { // deep
				if ($node->tagName == 'error') {
					$array = array(
						'id' => $node->getAttribute('id'),
						'code' => $node->getAttribute('code'),
						'auxCode' => $node->getAttribute('auxCode'),
						'message' => $node->nodeValue
					);
				} else {
					// in the case of custom charges, use the id for the key
					if ($node->hasAttribute('code') && $node->getAttribute('code')) {  
						$key = $node->getAttribute('code');
						
						$tmpArr = array();
						
						if ($node->hasAttribute('id')) {
							$tmpArr = array(
								'id' => $node->getAttribute('id')
							);
						}
						$tmpArr = $tmpArr + array('code'=>$key);
						
						// charges need to be a nested array since there can be multiple charges with the same charge code
						if ($node->tagName == 'charge') {
							$array[$key][] = $tmpArr;
						} else {
							$array[$key] = $tmpArr;
						}
						
						unset($tmpArr);
						
					} else {
						$key = $node->getAttribute('id');
						$array[$key] = array();
					}
					$array[$key] = $array[$key] + $this->_toArray($node->childNodes);
				}
			} else {
				if ($node->tagName == 'errors') {
					$array[$node->tagName][] = $this->_toArray($node->childNodes);
				} else if ($node->childNodes->length > 1) { // sub array
					$array[$node->tagName] = $this->_toArray($node->childNodes);
				} else {
					$array[$node->tagName] = $node->nodeValue;
				}
			}
		}
		return $array;
	}
	
	/**
	 * Get an array representation of a single plan node
	 *
	 * @throws CheddarGetter_Response_Exception if the response type is incompatible or if a $code is not provided and the response contains more than one plan
	 * @return array
	 */
	public function getPlan($code = null) {
		if ($this->getResponseType() != 'plans') {
			throw new CheddarGetter_Response_Exception("Can't get a plan from a response doc that isn't of type 'plans'", CheddarGetter_Response_Exception::USAGE_INVALID);
		}
		if (!$code && $this->getElementsByTagName('plan')->length > 1) {
			throw new CheddarGetter_Response_Exception("This response contains more than one plan so you need to provide the code for the plan you wish to get", CheddarGetter_Response_Exception::USAGE_INVALID);
		}
		if (!$code) {
			return current($this->toArray());
		}
		$array = $this->toArray();
		return $array[$code];
	}
	
	/**
	 * Get an array representation of all of the plan items
	 *
	 * @throws CheddarGetter_Response_Exception if the response type is incompatible or if a $code is not provided and the response contains more than one plan
	 * @return array
	 */
	public function getPlanItems($code = null) {
		$plan = $this->getPlan($code);
		return $plan[$items];
	}
	
	/**
	 * Get an array representation of a single plan item node
	 *
	 * @throws CheddarGetter_Response_Exception if the response type is incompatible or if a $code is not provided and the response contains more than one plan
	 * @return array
	 */
	public function getPlanItem($code = null, $itemCode = null) {
		$items = $this->getPlanItems($code);
		if (!$itemCode && count($items) > 1) {
			throw new CheddarGetter_Response_Exception("This plan contains more than one item so you need to provide the code for the item you wish to get", CheddarGetter_Response_Exception::USAGE_INVALID);
		}
		if (!$itemCode) {
			return current($items);
		}
		return $items[$itemCode];
	}
	
	/**
	 * Get an array representation of a single customer node
	 *
	 * @throws CheddarGetter_Response_Exception if the response type is incompatible or if a $code is not provided and the response contains more than one customer
	 * @return array
	 */
	public function getCustomer($code = null) {
		if ($this->getResponseType() != 'customers') {
			throw new CheddarGetter_Response_Exception("Can't get a customer from a response doc that isn't of type 'customers'", CheddarGetter_Response_Exception::USAGE_INVALID);
		}
		if (!$code && $this->getElementsByTagName('customer')->length > 1) {
			throw new CheddarGetter_Response_Exception("This response contains more than one customer so you need to provide the code for the customer you wish to get", CheddarGetter_Response_Exception::USAGE_INVALID);
		}
		if (!$code) {
			return current($this->toArray());
		}
		$array = $this->toArray();
		return $array[$code];
	}
	
	/**
	 * Get an array representation of a single customer's current subscription
	 *
	 * @throws CheddarGetter_Response_Exception if the response type is incompatible or if a $code is not provided and the response contains more than one customer
	 * @return array
	 */
	public function getCustomerSubscription($code = null) {
		$customer = $this->getCustomer($code);
		return current($customer['subscriptions']);
	}
	
	/**
	 * Get an array representation of a single customer's currently subscribed plan
	 *
	 * @throws CheddarGetter_Response_Exception if the response type is incompatible or if a $code is not provided and the response contains more than one customer
	 * @return array
	 */
	public function getCustomerPlan($code = null) {
		$subscription = $this->getCustomerSubscription($code);
		return current($subscription['plans']);
	}
	
	/**
	 * Get an array representation of a single customer's current invoice
	 *
	 * @throws CheddarGetter_Response_Exception if the response type is incompatible or if a $code is not provided and the response contains more than one customer
	 * @return array
	 */
	public function getCustomerInvoice($code = null) {
		$subscription = $this->getCustomerSubscription($code);
		return current($subscription['invoices']);
	}
	
	/**
	 * Get an array representation of a single customer's outstanding invoices
	 *
	 * @throws CheddarGetter_Response_Exception if the response type is incompatible or if a $code is not provided and the response contains more than one customer
	 * @return array|false
	 */
	public function getCustomerOutstandingInvoices($code = null) {
		$subscription = $this->getCustomerSubscription($code);
		foreach ($subscription['invoices'] as $key => $i) {
			// if the billing date is in the future or has been paid
			if ($i['paidTransactionId'] || strtotime($i['billingDatetime']) > time()) {
				unset($subscription['invoices'][$key]);
			}
		}
		if ($subscription['invoices']) {
			return $subscription['invoices'];
		}
		return false;
	}
	
	
		/**
	 * Get an array representation of a single customer's outstanding invoices
	 *
	 * @throws CheddarGetter_Response_Exception if the response type is incompatible or if a $code is not provided and the response contains more than one customer
	 * @return array|false
	 */
	public function getCustomerInvoices($code = null) {
		$data = array();
		$subscription = $this->getCustomerSubscription($code);
		foreach ($subscription['invoices'] as $key => $i) {
			// if the billing date is in the future or has been paid
			if ($i['paidTransactionId'] && strtotime($i['billingDatetime']) < time()) {
				$data[] = $i;
			}
		}
		return $data;
	}
	
	/**
	 * Get an array of a customer's item quantity and quantity included
	 *
	 * @throws CheddarGetter_Response_Exception if the response type is incompatible or if a $code is not provided and the response contains more than one customer or if a $itemCode is not provided and the plan contains more than one tracked item
	 * @return array 2 keys: 'item' (item config for this plan) and 'quantity' the customer's current quantity usage
	 */
	public function getCustomerItemQuantity($code = null, $itemCode = null) {
		$subscription = $this->getCustomerSubscription($code);
		if (!$itemCode && count($subscription['items']) > 1) {
			throw new CheddarGetter_Response_Exception("This customer's subscription contains more than one item so you need to provide the code for the item you wish to get", CheddarGetter_Response_Exception::USAGE_INVALID);
		}
		$plan = $this->getCustomerPlan($code);
		if ($itemCode) {
			$item = $plan['items'][$itemCode];
			$itemQuantity = $subscription['items'][$itemCode];
		} else {
			$item = current($plan['items']);
			$itemQuantity = current($subscription['items']);
		}
		return array(
			'item' => $item,
			'quantity' => $itemQuantity
		);
	}
	
	public function handleError() {
		if ($this->_responseType == 'error') {
			throw new CheddarGetter_Response_Exception($this->documentElement->firstChild->nodeValue, $this->documentElement->getAttribute('code'), $this->documentElement->getAttribute('id'), $this->documentElement->getAttribute('auxCode'));
		}
		return false;
	}
	
	/**
	 * Checks for embedded errors and if found, throws an exception containing the first error
	 *
	 * Embedded errors typically occur when some transaction-related action is performed as part of another request.  For example, if a customer is created and subscribed to a paid plan with a setup fee, the customer is created and the credit card is validated and then a transaction is run for the amount of the setup fee.  If the transaction fails, there will be an embedded error with information about the failed transaction.
	 *
	 * @throws CheddarGetter_Response_Exception if there are embedded errors in the response
	 * @return boolean false if no embedded errors
	 */
	public function handleEmbeddedErrors() {
		if ($this->hasEmbeddedErrors()) {
			$errors = $this->getEmbeddedErrors();
			$error = $errors[0];
			throw new CheddarGetter_Response_Exception(
				$error['message'], 
				$error['code'], 
				$error['id'], 
				$error['auxCode']
			);
		}
		return false;
	}
	
	public function __toString() {
		return $this->saveXML();
	}
	
}
