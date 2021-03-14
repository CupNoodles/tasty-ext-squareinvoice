## Square Invoice

Creates an invoice in squareup.com from an existing TastyIgniter order. This extension registers a placeholder payment method which doesn't take in any payment data at checkout. 

### Dependancies

This extension will install the PHP square SDK https://github.com/square/square-php-sdk.
Common usage will depend on either a variable delivery fee extension such as cupnoodles.postmates or an order editor such sa cupnoodles.ordermenuedit.

### Installation

Clone these files into `extensions/cupnoodles/squareinvoice/`. While a marketplace install automatically installs composer, manual installation requires that you run `composer install` within the extension directory in order to create the required `vendor/` files. 

### Usage 

Square Invoices are used for a very specific order flow that will not be useful for most restaraunt enviroments. The currently intended usage is as follows:

- Customer creates an order with the Square Invoice payment method, which takes in no extra data and doesn't make any charges. 
- On the backend, kitchen staff can update the order contents and cost through an order editing extension such as cupnoodles.ordermenuedit.
- Once the order is in the desired state, kitchen staff can click a button on the admin order view which 
  - creates a Square Order (note that an Order ID will be generated and saved to the orders table, but unpaid order do *not* show up in the Square dashboard under 'Orders')
  - creates a Square Invoice based on the Order ID. Invoices can be see in the Square admin dashboard.
  - publishes the Square Invoice, which will automatically send the invoice link to the customer email address supplied in the order. 

