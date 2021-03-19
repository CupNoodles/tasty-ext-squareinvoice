<?php 

namespace CupNoodles\SquareInvoice;

use Admin\Models\Menus_model;
use System\Classes\BaseExtension;
use DB;
use Event;
use App;
use Igniter\Flame\Exception\ApplicationException;

use Admin\Widgets\Form;
use Admin\Models\Orders_model;
use Admin\Models\Locations_model;
use Admin\Widgets\Toolbar;
use Admin\Controllers\Orders;
use System\Classes\ExtensionManager;

use CupNoodles\SquareInvoice\Models\SquareInvoiceSettings;




use Square\SquareClient;
use Square\LocationsApi;
use Square\Exceptions\ApiException;
use Square\Http\ApiResponse;
use Square\Models\ListLocationsResponse;
use Square\Environment;

use Square\Models\CreateOrderRequest;
use Square\Models\UpdateOrderRequest;
use Square\Models\Order;
use Square\Models\OrderSource;

use Square\Models\SearchCustomersRequest;

use Square\Models\CustomerQuery;
use Square\Models\CustomerFilter;
use Square\Models\CustomerTextFilter;

use Square\Models\CreateCustomerRequest;

use Square\Models\OrderLineItem;
use Square\Models\OrderQuantityUnit;
use Square\Models\Money;

use Square\Models\Invoice;
use Square\Models\InvoiceRecipient;
use Square\Models\InvoicePaymentRequest;
use Square\Models\InvoiceDeliveryMethod;
use Square\Models\CreateInvoiceRequest;
use Square\Models\InvoiceRequestType;
use Square\Models\PublishInvoiceRequest;
use Square\Models\MeasurementUnit;
use Square\Models\MeasurementUnitCustom;

class Extension extends BaseExtension
{
    /**
     * Returns information about this extension.
     *
     * @return array
     */
    public function extensionMeta()
    {
        return [
            'name'        => 'SquareInvoice',
            'author'      => 'CupNoodles',
            'description' => 'Square Invoice generator from TastyIgniter Admin',
            'icon'        => 'fa-file-invoice',
            'version'     => '1.0.0'
        ];
    }

    /**
     * Register method, called when the extension is first registered.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        // global
        $this->client = $this->createSquareClient();

        // admin

        // Add Location ID parameter to each location
        Event::listen('admin.form.extendFieldsBefore', function (Form $form) {

            if ($form->model instanceof Locations_model) {
                $location_id = [
                    'tab' => 'lang:cupnoodles.squareinvoice::default.location_tab',
                    'label' => 'lang:cupnoodles.squareinvoice::default.square_location_id_label',
                    'type' => 'text',
                    'span' => 'left',

                ];
                $form->tabs['fields']['square_location_id'] = $location_id;
            }
        });
        
        Event::listen('admin.form.extendFieldsBefore', function (Form $form) {

            if ($form->model instanceof Orders_model) {

                if(    SquareInvoiceSettings::get('enable_square_invoices_on_pickup') && $form->model->isCollectionType()
                    || SquareInvoiceSettings::get('enable_square_invoices_on_delivery') && $form->model->isDeliveryType()
                ){
                    if($form->model->square_invoice_id == ''){
                        Event::listen('admin.toolbar.extendButtons', function (Toolbar $toolbar) use ($form) {
                            $toolbar->buttons['send_square_invoice']  = [
                                'label' => 'lang:cupnoodles.squareinvoice::default.create_square_invoice_button',
                                'class' => 'btn btn-primary',
                                'data-request' => 'onSquareInvoice',
                                'data-request-data' => "_method:'POST', order_id:" . $form->model->order_id . ", refresh:1",
                                'data-request-confirm' => 'lang:cupnoodles.squareinvoice::default.create_square_invoice_confirmation',
                            ];
                        });	
                    }
                }				
            }
        });


        Orders::extend(function($controller){
            $controller->addDynamicMethod('edit_onSquareInvoice', function($action, $order_id) use ($controller) {
                $model = $controller->formFindModelObject($order_id);

                $square_order_id = $this->createSquareOrder($model);

                if ($redirect = $controller->makeRedirect('edit', $model)) {
                    return $redirect;
                }

            });
        } );
    
    }

    public function createSquareClient(){

        if(!SquareInvoiceSettings::get('production_mode')){
            $client = new SquareClient([
                'accessToken' =>  SquareInvoiceSettings::get('sandbox_access_token') ,
                'environment' => Environment::SANDBOX,
            ]);
        }
        else{
            $client = new SquareClient([
                'accessToken' =>  SquareInvoiceSettings::get('production_access_token') ,
                'environment' => Environment::PRODUCTION,
            ]);
        }

        return $client;
    }

    /*
    *  returns a string square customers id
    */
    public function searchCreateCustomer($email_address){

        $customersApi = $this->client->getCustomersApi();

        $body = new SearchCustomersRequest;
        $body->setLimit(1);
        $body->setQuery(new CustomerQuery);
        $body->getQuery()->setFilter(new CustomerFilter);
        $body->getQuery()->getFilter()->setEmailAddress(new CustomerTextFilter);
        $body->getQuery()->getFilter()->getEmailAddress()->setExact($email_address);
        
        
        $apiResponse = $customersApi->searchCustomers($body);

        if ($apiResponse->isSuccess()) {
            $searchCustomersResponse = $apiResponse->getResult();
            if(count($searchCustomersResponse->getCustomers())){
                return $searchCustomersResponse->getCustomers()[0]->getId();
            }
            else{
                return $this->createSquareCustomer($email_address);
            }
        } else {
            $errors = $apiResponse->getErrors();
            throw new ApplicationException(print_r($errors, true) );
        }
    }

    public function createSquareCustomer($email_address){
        $customersApi = $this->client->getCustomersApi();

        $body = new CreateCustomerRequest;
        $body->setIdempotencyKey(md5($email_address));
        $body->setEmailAddress($email_address);

        $apiResponse = $customersApi->createCustomer($body);

        if ($apiResponse->isSuccess()) {
            $createCustomerResponse = $apiResponse->getResult();
            return $createCustomerResponse->getCustomer()->getId();

        } else {
            $errors = $apiResponse->getErrors();
            throw new ApplicationException(print_r($errors, true) );
        }
    }

    public function createSquareOrder($orders_model){

        $ordersApi = $this->client->getOrdersApi();

        $manager = ExtensionManager::instance();
        // if cupnoodles.pricebyweight is enabled, item counts may be fractional an therefore need special treatment within the square invoice
        $price_by_weight_enabled = false;
        $extension = $manager->findExtension('cupnoodles.pricebyweight');
        if($extension && $extension->disabled == false){
            $price_by_weight_enabled = true;
        }

        // if cupnoodles.ordermenuedit is enabled, we want to send the ->actual_amt field instead of the ->quantity field
        $order_menu_edit_enabled = false;
        $extension = $manager->findExtension('cupnoodles.ordermenuedit');
        if($extension && $extension->disabled == false){
            $order_menu_edit_enabled = true;
        }

        $body = new CreateOrderRequest;
 
        if(!SquareInvoiceSettings::get('production_mode')){
            $body_order_locationId = SquareInvoiceSettings::get('sandbox_location_id');
        }
        else{
            $body_order_locationId = Locations_model::where('location_id', $orders_model->location_id)->first()->square_location_id;
        }
        
        $square_customer_id = $this->searchCreateCustomer($orders_model->email);
        $body->setOrder(new Order(
            $body_order_locationId
        ));

        $body->getOrder()->setReferenceId($orders_model->invoice_number);
        $body->getOrder()->setCustomerId($square_customer_id);
        $body->setIdempotencyKey($orders_model->hash . 'hi');
    
        $body_order_lineItems = [];
        $menus = $orders_model->getOrderMenusWithOptions();
        foreach ($menus as $ix=>$menu) {

            if($order_menu_edit_enabled){
                $quantity = ($menu->actual_amt == '' ? $menu->quantity : $menu->actual_amt);
            }
            else{
                $quantity = $menu->quantity;
            }
            
            $body_order_lineItems[$ix] = new OrderLineItem($quantity);
            $body_order_lineItems[$ix]->setName($menu->name);

            if($price_by_weight_enabled && isset($menu->uom_tag) && $menu->uom_tag != ''){
                $body_order_lineItems[$ix]->setQuantityUnit(new OrderQuantityUnit);
                $body_order_lineItems[$ix]->getQuantityUnit()->setMeasurementUnit(new MeasurementUnit);
                $body_order_lineItems_0_quantityUnit_measurementUnit_customUnit_name = $menu->uom_tag;
                $body_order_lineItems_0_quantityUnit_measurementUnit_customUnit_abbreviation = $menu->uom_tag;
                $body_order_lineItems[$ix]->getQuantityUnit()->getMeasurementUnit()->setCustomUnit(new MeasurementUnitCustom(
                    $body_order_lineItems_0_quantityUnit_measurementUnit_customUnit_name,
                    $body_order_lineItems_0_quantityUnit_measurementUnit_customUnit_abbreviation
                ));
                $body_order_lineItems[$ix]->getQuantityUnit()->setPrecision($menu->uom_decimals);
            }

            $body_order_lineItems[$ix]->setBasePriceMoney(new Money);
            $body_order_lineItems[$ix]->getBasePriceMoney()->setAmount((int)($menu->price*100));
            
            // ->setCurrency() in the Square SDK expects on of it's own defined constants, which we're assuming matches the iso3 code entered into TI. 
            $body_order_lineItems[$ix]->getBasePriceMoney()->setCurrency(app('currency')->getDefault()->getCode());
            
        }

        $body->getOrder()->setLineItems($body_order_lineItems);


        try {
            
            $apiResponse = $ordersApi->createOrder($body);
            if ($apiResponse->isSuccess()) {

                $createOrderResponse = $apiResponse->getResult();
                $orders_model->square_order_id = $createOrderResponse->getOrder()->getId();
                $orders_model->save();
                $orders_model->updateOrderStatus(null, ['comment' => 'New order profile created in Square with ID ' . $orders_model->square_order_id . '.']);
                $this->createSquareOrderInvoice($orders_model);
            } else {
                $errors = $apiResponse->getErrors();
                throw new ApplicationException(print_r($errors, true) );
            }
        }
        catch (ApiException $e) {
            throw new ApplicationException($e->getMessage());
        } 


    }

    public function createSquareOrderInvoice($orders_model){

        $body_invoice = new Invoice;

        $invoicesApi = $this->client->getInvoicesApi();

        if(!SquareInvoiceSettings::get('production_mode')){
            $locationId = SquareInvoiceSettings::get('sandbox_location_id');
        }
        else{
            $locationId = Locations_model::where('location_id', $orders_model->location_id)->first()->square_location_id;
        }
        $body_invoice->setLocationId($locationId);
        $body_invoice->setOrderId($orders_model->square_order_id);
        $body_invoice->setPrimaryRecipient(new InvoiceRecipient);
         
        $square_customer_id = $this->searchCreateCustomer($orders_model->email);
        $body_invoice->getPrimaryRecipient()->setCustomerId($square_customer_id);

        $body_invoice_paymentRequests = [];

        $body_invoice_paymentRequests[0] = new InvoicePaymentRequest;
        $body_invoice_paymentRequests[0]->setRequestType(InvoiceRequestType::BALANCE);
        $body_invoice_paymentRequests[0]->setDueDate(date('Y-m-d', strtotime('+' . SquareInvoiceSettings::get('invoice_due_date_days'). ' days')));
        $body_invoice_paymentRequests[0]->setTippingEnabled(false);
        $body_invoice->setPaymentRequests($body_invoice_paymentRequests);

        $body_invoice->setDeliveryMethod(InvoiceDeliveryMethod::EMAIL);

        $body_invoice->setInvoiceNumber($orders_model->invoice_number);
        $body_invoice->setTitle(str_replace('{{INV_NUM}}', $orders_model->invoice_nuner, SquareInvoiceSettings::get('invoice_title')));
        $body_invoice->setDescription(SquareInvoiceSettings::get('invoice_description'));
        $body = new CreateInvoiceRequest(
            $body_invoice
        );
        $body->setIdempotencyKey(md5($orders_model->invoice_number . 'invoice'));

        $apiResponse = $invoicesApi->createInvoice($body);

        if ($apiResponse->isSuccess()) {
            $createInvoiceResponse = $apiResponse->getResult();

            $orders_model->square_invoice_id = $createInvoiceResponse->getInvoice()->getId();
            $orders_model->save();
            $orders_model->updateOrderStatus(null, ['comment' => 'New invoice created in Square with ID <a target="_blank" href="https://'.(SquareInvoiceSettings::get('production_mode') ? 'squareup' : 'squareupsandbox').'.com/dashboard/invoices/' . $orders_model->square_invoice_id . '">' . $orders_model->square_invoice_id . '</a>.']);
            $this->publishSquareInvoice($orders_model);

        } else {
            $errors = $apiResponse->getErrors();
            throw new ApplicationException(print_r($errors, true) );
        }


    }

    public function publishSquareInvoice($orders_model){

        $invoicesApi = $this->client->getInvoicesApi();

        $invoiceId = $orders_model->square_invoice_id;
        $body_version = 0;
        $body = new PublishInvoiceRequest(
            $body_version
        );
        $body->setIdempotencyKey(md5($orders_model->hash . 'invoice_publish'));
        
        $apiResponse = $invoicesApi->publishInvoice($invoiceId, $body);
        
        if ($apiResponse->isSuccess()) {
            $publishInvoiceResponse = $apiResponse->getResult();
        } else {
            $errors = $apiResponse->getErrors();
            throw new ApplicationException(print_r($errors, true) );
        }
        
    }

    public function registerFormWidgets()
    {

    }

    /**
     * Registers any front-end components implemented in this extension.
     *
     * @return array
     */
    public function registerComponents()
    {

    }

    public function registerPaymentGateways()
    {
        return [
            'CupNoodles\SquareInvoice\Payments\SquareInvoice' => [
                'code' => 'squareinvoice',
                'name' => 'lang:cupnoodles.squareinvoice::default.text_payment_title',
                'description' => 'lang:cupnoodles.squareinvoice::default.text_payment_desc',
            ],
        ];
    }


    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => 'Square Invoice Settings',
                'description' => 'Manage Square Invoice API settings.',
                'icon' => 'fa fa-file-invoice',
                'model' => 'CupNoodles\SquareInvoice\Models\SquareInvoiceSettings',
                'permissions' => ['Module.SquareInvoice'],
            ],
        ];
    }




    /**
     * Registers any admin permissions used by this extension.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [

        ];
    }

    public function registerNavigation()
    {
        return [

        ];
    }



}
