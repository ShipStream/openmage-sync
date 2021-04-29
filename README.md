ShipStream <=> OpenMage Sync Extension
==========================

This is an extension for OpenMage and Magento 1 (CE or EE) which facilitates efficient synchronization
between ShipStream and OpenMage/Magento. This extension on its own will have no effect and
requires the corresponding plugin to be setup in ShipStream.

### What functionality does this extension add to my OpenMage/Magento store?

- Adds API endpoint `shipstream_stock_item.adjust`
- Adds API endpoint `shipstream_order_shipment.info`
- Adds API endpoint `shipstream_config.set`
- Adds a cron job to do a full inventory pull at 02:00 every day (with a random sleep time)
- Adds a new order status: "Submitted"
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
   
### What is the point of the new "Submitted" status?

Without a state between "Pending" and "Complete" it is otherwise difficult to tell if an order
has been successfully submitted to the warehouse and if the order has been processed at the
warehouse. The "Submitted" status indicates that it was successfully submitted to the warehouse,
but that it has not yet been fully shipped. The intermediate "Submitted" status is automatically
skipped for "virtual" orders that do not contain physical goods.

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

- Core / Magento info / Retrieve info about current Magento installation
  - *provides basic info to prove successful authentication*
- Sales / Order / Change status, add comments
  - *set the order status to Complete after fulfillment*
- Sales / Order / Retrieve orders info
  - *get basic order information pertinent to fulfillment*
- Sales / Order / Order shipments / Create
  - *create a OpenMage Shipment when an order is accepted from manual sync or Auto-Fulfill*
- Sales / Order / Order shipments / Tracking
  - *add tracking numbers when a shipment is packed*
- Sales / Order / Order shipments / Retrieve shipment info
  - *receive user-created Shipments when Auto-Fulfill is disabled*
- ShipStream Sync
  - *the custom API methods added by this extension*

# Customization

A common customization you may want to make is to create a new order status such as "Ready for Fulfillment"
and then configure the ShipStream plugin to only sync orders with this status. This way you can easily
control which orders are submitted to ShipStream either manually or programmatically.

Feel free to modify this source code to fit your specific needs. For example if you have multiple
fulfillment providers you may want to add some metadata to the orders so that they will not be
imported automatically.

# Support

For help just email us at [help@shipstream.io](mailto:help@shipstream.io)!