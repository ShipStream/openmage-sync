ShipStream <=> OpenMage Sync Extension
==========================

This is an extension for OpenMage and Magento 1 (CE or EE) which facilitates efficient synchronization
between ShipStream and OpenMage/Magento. This extension on its own will have no effect and
requires the corresponding plugin to be setup in ShipStream.

### What functionality does this extension add to my OpenMage/Magento store?

- Adds a System > Configuration section to Sales > Shipping Settings > ShipStream Sync
  - Enable Real-Time Order Sync
  - Send New Shipment Email
- Adds three new order statuses: "Ready to Ship", "Failed to Submit" and "Submitted"
- Adds API endpoint `shipstream.info`
- Adds API endpoint `shipstream.set_config`
- Adds API endpoint `shipstream.sync_inventory`
- Adds API endpoint `shipstream_stock_item.adjust`
- Adds API endpoint `shipstream_order_shipment.info`
- Adds API endpoint `shipstream_order_shipment.createWithTracking`
- Adds a cron job to do a full inventory pull at 02:00 every day (with a random sleep time)
- Stores the ShipStream remote url in the `core_flag` table

### Why is this required? Doesn't OpenMage/Magento already have an API?

Yes, but there are several shortcomings that this extension addresses:

1. When syncing remote systems over basic HTTP protocol there is no locking between
   when the inventory amount is known and when it is updated, so setting an exact quantity
   for inventory levels is prone to race conditions. Adding the "adjust" method allows
   ShipStream to push stock adjustments (e.g. +20 or -10) rather than absolute values
   which are far less susceptible to race condition-induced errors.
1. The OpenMage Catalog Inventory API does not use proper database-level locking so is
   also prone to local race conditions. This module uses `SELECT ... FOR UPDATE` to ensure
   that updates are atomic at the database level.
1. This module implements a "pull" mechanism so that OpenMage can lock the inventory locally
   and remotely, take a snapshot of local inventory and remote inventory, then sync the inventory
   amounts all inside of one database transaction. This ensures that inventory does not drift
   or hop around due to race conditions. For a high-volume store these issues are much more frequent
   than you might imagine.
1. The OpenMage API `shipment.info` method returns SKUs that do not reflect the simple
   product SKU which is used by ShipStream. This extension returns SKUs that are appropriate
   for the WMS to use.
1. Four or more API calls can be cut down to one with the `shipstream_order_shipment.createWithTracking`
   method which also allows for easy customization (e.g. receiving and storing serial numbers or lot data).
   
### What is the point of the new statuses?

Without a state between "Processing" and "Complete" it is otherwise difficult to tell if an order
is ready to be submitted to the warehouse, if it has been successfully submitted to the warehouse,
or if the order has been processed at the warehouse.

The "Ready to Ship" status can be set manually or programmatically (using some custom code with your own business logic)
to indicate when an order is ready to be shipped. If you don't want to use this status simply ignore it and configure
ShipStream to pull orders in "Processing" status instead.

The "Failed to Submit" status indicates that there was an error when trying to create the order at the
warehouse.

The "Submitted" status indicates that it was successfully submitted to the warehouse, but that it has
not yet been fully shipped. Once it is fully shipped the order status will automatically advance to "Complete".

*Note:* Feel free to change the status labels of these new statuses but do not change the status codes to avoid
breaking the integration.

If the ShipStream plugin is configured to sync orders that are in "Ready to Ship" status the order status progression
will work as depicted below. Note that this requires a user or some custom code to advance the order status from
"Processing" to "Ready to Ship" before the sync will occur.  

![Status State Diagram](https://raw.githubusercontent.com/ShipStream/openmage-sync/master/shipstream-sync.png)

If the ShipStream plugin is configured to sync orders that are in "Processing" status the order status progression
will work as depicted below. This configuration will result in automatic order sync without any user interaction
once payment is received.

![Status State Diagram](https://raw.githubusercontent.com/ShipStream/openmage-sync/master/shipstream-sync-processing.png)

It is also possible to configure the ShipStream plugin to use any other status in the event that you would like to create
a custom workflow.


Installation
============

You can install this extension using Composer, modman or zip file. Flush the OpenMage cache after
installation.

#### modman

```
$ modman init
$ modman clone https://github.com/ShipStream/openmage-sync
```

#### Composer

[comment]: <> (Add Firegento package repository to your repositories if you haven't already done so:)

[comment]: <> (```json)

[comment]: <> (  "repositories": [)

[comment]: <> (    {)

[comment]: <> (      "type": "composer",)

[comment]: <> (      "url": "http://packages.firegento.com")

[comment]: <> (    })

[comment]: <> (  ],)

[comment]: <> (```)

Run `composer require` to pull in the extension to your composer environment.

```
$ composer require shipstream/openmage-sync
```

#### Zip file

1. Download the latest release from Github releases page: TODO
1. Extract the contents into your OpenMage/Magento source directory.

Setup
=====

Once this extension is installed and the Magento cache has been refreshed you have only three steps:

1. Configure the plugin in OpenMage/Magento
2. Create and API Role and API User
3. Setup the plugin subscription in ShipStream 

## Configuration

Adjust configuration in System > Configuration > Sales > Shipping Settings > ShipStream Sync section to your needs.

## Create API User

This extension does not require any setup other than to create an API Role and API User for the
ShipStream plugin.

1. Navigate to "System > Web Services > SOAP/XML-RPC - Roles"
1. Click "Add New Role"
1. Provide the name "ShipStream" and your current admin password
1. Click the "Role Resources" tab and select the resources listed in the section below
1. Click "Save Role"
1. Navigate to "System > Web Services > SOAP/XML-RPC - Users"
1. Click "Add New User"
1. Provide the form fields choosing a secure API Key and note the "User Name" and "API Key" for later use
1. Click the "User Role" tab and select the "ShipStream" role created in the previous steps
1. Click "Save User"
1. Provide the User Name and API Key to the ShipStream subscription configuration in ShipStream

### Required API Role Resources

The following Role Resources are required for best operation with the ShipStream plugin:

- Sales / Order / Change status, add comments
  - *set the order status to Complete after fulfillment*
- Sales / Order / Retrieve orders info
  - *get basic order information pertinent to fulfillment*
- ShipStream Sync
  - *the custom API methods added by this extension*

## ShipStream Setup

The ShipStream plugin will need the API URL of your store which should be the base url ending with `/api/soap/`
and the API User and API Password created in the step above.

# Customization

Feel free to modify this source code to fit your specific needs. For example if you have multiple
fulfillment providers you may want to add some metadata to the orders so that they will not be
imported automatically.

# Support

For help just email us at [help@shipstream.io](mailto:help@shipstream.io)!