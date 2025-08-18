<?php
/* * ********************************************************************
 * Gateway Control by WHMCS Services
 *
 * Created By WHMCSServices      http://www.whmcsservices.com
 * Contact:	      		 dev@whmcsservices.com
 *
 * This software is furnished under a license and may be used and copied
 * only  in  accordance  with  the  terms  of such  license and with the
 * inclusion of the above copyright notice.  This software  or any other
 * copies thereof may not be provided or otherwise made available to any
 * other person.  No title to and  ownership of the  software is  hereby
 * transferred.
 * ******************************************************************** */
use WHMCS\Database\Capsule;


if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}




function gateway_control_config($vars = NULL)
{
    
    $configarray = array(
        // Display name for your module
        'name' => 'Gateway Control',
        // Description displayed within the admin interface
        'description' => 'Using this module, the admin user can restrict specific payment gateways for renewals and define an alternative gateway to be used when a restricted gateway is selected for renewal.',
        // Module author name
        "author" => "<a href='https://www.whmcsservices.com' target='_blank'>WHMCS Services</a>",
       
        // Default language
        'language' => 'english',
        // Version number
        'version' => '1.0.0',
        'fields' => [
           
        ]
    );
   
    return $configarray;
}

function gateway_control_activate()
{
    try {
        if (!Capsule::schema()->hasTable('ws_bypass_gateway_client')) {
            Capsule::schema()->create('ws_bypass_gateway_client', function ($table) {
                $table->increments('id'); // Auto-increment unique ID
                $table->integer('client_id')->unique(); // Unique client ID
                $table->timestamps(); // created_at and updated_at timestamps
            });
        }
       
        
        return [
            'status' => 'success',
            'description' => 'Gateway Control module activated successfully.',
        ];
    } catch (\Exception $e) {
        // Log any error that occurs
        logActivity('Error during addon activation: ' . $e->getMessage());
        return [
            'status' => 'error',
            'description' => 'Gateway Control :- An error occurred: ' . $e->getMessage(),
        ];
    }
  
}


function gateway_control_deactivate()
{
    
    return [
        // Supported values here include: success, error or info
        'status' => 'success',
        'description' => 'Module Gateway Control Deactivated Successfully',
    ];
}
function gateway_control_output($vars)
{
    
    
    $modulename = "gateway_control";
    $LANG = $vars["_lang"];

    // Variable to store success or error message
    $message = '';

    
   
    // Navigation Bar
    echo '<nav class="navbar navbar-default">
        <div class="container-fluid">
            <div class="navbar-header" style="float: right">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#mod_apu_bt_navbar_menu" aria-expanded="false" aria-controls="navbar">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <!---- <a href="addonmodules.php?module=' . $modulename . '&page=about" style="line-height:13px;color:#034048;text-shadow:0.3px 0.3px 0.4px #0f7d8c" class="navbar-brand">Help Center<br><span style="font-size:10px;color:#f53f3f;text-shadow:0.3px 0.3px 0.4px #ff0d0d">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></a> -->
            </div>
            <div class="collapse navbar-collapse" id="mod_apu_bt_navbar_menu">
                <ul class="nav navbar-nav">
                    <li><a href="addonmodules.php?module=' . $modulename . '">' . ((!isset($_REQUEST['page'])) ? '<b>Setting</b>' : 'Setting') . '</a></li>
                    <li><a href="addonmodules.php?module=' . $modulename . '&page=bypass_client">' . (($_REQUEST['page'] == "bypass_client") ? '<b>Bypass Client</b>' : 'Bypass Client') . '</a></li>
                    
                </ul>
            </div>
        </div>
    </nav>';
    // Include external CSS and JS for Select2
    echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css" rel="stylesheet" />';
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js"></script>';

    if($_REQUEST['page'] == "bypass_client"){
       
        $message = '';
        $messageType = '';

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $clientId = intval($_POST['client_id']);

            // Check if client ID already exists in the custom table
            $exists = Capsule::table('ws_bypass_gateway_client')->where('client_id', $clientId)->exists();

            if ($exists) {
                $message = 'Client already stored for bypass.';
                $message_class = 'danger';
            } else {
                // Get the current timestamp
                $currentTimestamp = date('Y-m-d H:i:s');
                // Insert the client ID into the database// Insert the client ID into the database with timestamps
                Capsule::table('ws_bypass_gateway_client')->insert([
                    'client_id' => $clientId,
                    'created_at' => $currentTimestamp,
                    'updated_at' => $currentTimestamp,
                ]);
                $message = 'Client stored for bypass successfully!!';
                $message_class = 'success';
            }
        }
        // Handle delete request
        if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
            $deleteId = intval($_GET['delete']);
            Capsule::table('ws_bypass_gateway_client')->where('id', $deleteId)->delete();
            $message = 'Client bypass record deleted successfully!';
            $message_class = 'success';
        }

        // Fetch all active clients
        $clients = Capsule::table('tblclients')
            ->where('status', 'Active')
            ->get(['id', 'firstname', 'lastname', 'companyname']);

        // Prepare client options for the select dropdown
        $clientOptions = '<option value="">-- Select a Client --</option>';
        foreach ($clients as $client) {
            $clientName = $client->firstname . ' ' . $client->lastname;
            if (!empty($client->companyname)) {
                $clientName .= ' (' . $client->companyname . ')';
            }
            $clientOptions .= '<option value="' . $client->id . '">' . htmlspecialchars($clientName) . '</option>';
        }

        // Display message if any
        if ($message) {
            echo '<div class="alert alert-' . $message_class . '">' . $message . '</div>';
        }

        // Form HTML
        echo '<form method="post" action="addonmodules.php?module=' . $modulename . '&page=bypass_client">
            <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
                <tbody>
                    <tr>
                        <td class="fieldlabel">Select Client</td>
                        <td class="fieldarea" style="width:30%;">
                            <select name="client_id" class="form-select select2" style="width:100%;" required>' . $clientOptions . '</select>
                        </td>
                        <td class="fieldarea" style="width:55%;">
                            Select a client from the dropdown to bypass the gateway replacement.
                        </td>
                    </tr>
                </tbody>
            </table>
            <p align="center">
                <input type="submit" class="btn btn-success" value="Save Client ID">
            </p>
        </form>';
        
       
        // Fetch all stored records
        $storedRecords = Capsule::table('ws_bypass_gateway_client')
        ->join('tblclients', 'ws_bypass_gateway_client.client_id', '=', 'tblclients.id')
        ->select('ws_bypass_gateway_client.id', 'tblclients.firstname', 'tblclients.lastname', 'tblclients.id as client_id', 'ws_bypass_gateway_client.created_at')
        ->get();

        // Table HTML
        echo '<table id="storedClientsTable" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Client Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
        $serialNumber = 1; // Initialize the serial number
        foreach ($storedRecords as $record) {
            $clientName = $record->firstname . ' ' . $record->lastname;
            $clientSummaryUrl = 'clientssummary.php?userid=' . $record->client_id;

            echo '<tr>
               <td>' . $serialNumber++ . '</td> <!-- Display Serial Number -->
                <td><a href="' . $clientSummaryUrl . '" target="_blank">' . htmlspecialchars($clientName) . '</a></td>
                <td>
                    <a href="addonmodules.php?module=' . $modulename . '&page=bypass_client&delete=' . $record->id . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete this record?\')">Delete</a>
                </td>
            </tr>';
        }
        echo '</tbody></table>';

        // Include DataTables and Select2 initialization script
        echo '<script>
            $(document).ready(function() {
                $(".select2").select2({
                    placeholder: "Select a Client",
                    allowClear: true,
                });
                $("#storedClientsTable").DataTable({
                    responsive: true,
                    pageLength: 10,
                });
            });
        </script>';

    }else{
        // Assuming the form is submitted via POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Fetch the settings values from the form
            $renewalRestrict = isset($_POST['settings']['renewal_restrict']) ? $_POST['settings']['renewal_restrict'] : '';
            $renewalRestrictReplacement = isset($_POST['settings']['renewal_restrict_replacement']) ? $_POST['settings']['renewal_restrict_replacement'] : '';

            try {
                // Process and save the settings in the database (assuming you have a settings table or similar)
                // Example using Capsule to save to a settings table (replace 'your_table_name' with actual table name)

                Capsule::table('tbladdonmodules')->updateOrInsert(
                    [
                        'module' => 'gateway_control', // Condition to match the existing record
                        'setting' => 'renewal_restrict', // First setting
                    ],
                    [
                        'value' => $renewalRestrict
                    ]
                );

                Capsule::table('tbladdonmodules')->updateOrInsert(
                    [
                        'module' => 'gateway_control', // Condition to match the existing record
                        'setting' => 'renewal_restrict_replacement', // First setting
                    ],
                    [
                        'value' => $renewalRestrictReplacement
                    ]
                );

                // Set success message
                $message = '<div class="alert alert-success">Settings saved successfully.</div>';

            } catch (Exception $e) {
                // Set error message if an exception occurs
                $message = '<div class="alert alert-danger">An error occurred while saving settings: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }


    
        $command = 'GetPaymentMethods';
        $postData = [];
        $results = localAPI($command, $postData);
        $settings = [];
        // Retrieve stored values from the database
        $storedRenewalRestrict = Capsule::table('tbladdonmodules')->where('module', 'gateway_control')->where('setting', 'renewal_restrict')->value('value'); // Replace with your database value for `renewal_restrict`
        $storedRenewalRestrictReplacement = Capsule::table('tbladdonmodules')->where('module', 'gateway_control')->where('setting', 'renewal_restrict_replacement')->value('value'); // Replace with your database value for `renewal_restrict_replacement`

        
        $paymentGatewaysRestrict = generateGatewayOptions($results, $storedRenewalRestrict);
        $paymentGatewaysRestrictReplacement = generateGatewayOptions($results, $storedRenewalRestrictReplacement);
        // Display message (success or error) at the top of the form
        if (!empty($message)) {
            echo $message;
        }

        echo '<form method="post" action="addonmodules.php?module=' . $modulename . '">
            <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
                <tbody>
                    <tr>
                        <td class="fieldlabel">' . $LANG["renewal_restrict"] . '</td>
                        <td class="fieldarea" style="width:30%;">
                            <select name="settings[renewal_restrict]" class="form-select select2" style="width:100%;">' . $paymentGatewaysRestrict . '</select> ' . $LANG["adminlogindetails"] . '
                        </td>
                        <td class="fieldarea" style="width:55%;">
                        ' . $LANG["renewal_restrict_desc"] . '
                        </td>
                    </tr>
                    <tr>
                        <td class="fieldlabel">' . $LANG["renewal_restrict_replacement"] . '</td>
                        <td class="fieldarea" style="width:30%;">
                            <select name="settings[renewal_restrict_replacement]" class="form-select select2" style="width:100%;">' . $paymentGatewaysRestrictReplacement . '</select> ' . $LANG["adminlogindetails"] . '
                        </td>
                        <td class="fieldarea" style="width:55%;">
                        ' . $LANG["renewal_restrict_replacement_desc"] . '
                        </td>
                    </tr>
                </tbody>
            </table>
            <p align="center">
                <input type="submit" class="btn btn-success" value="' . $LANG["savechanges"] . '">
            </p>
        </form>';
        echo '</tbody></table>
        <script>                  
            $(document).ready(function() {
                $(".select2").select2({
                    placeholder: "Select Payment Gateway",
                    allowClear: true,
                });
                
            });
        </script>';
    }
    
}
// Generate payment gateway options with default selections
function generateGatewayOptions($results, $storedValue)
{
    $options = '<option value="">None</option>';
    if ($results['result'] === 'success') {
        foreach ($results['paymentmethods']['paymentmethod'] as $gateway) {
            $gatewayName = htmlspecialchars($gateway['displayname']);
            $gatewayValue = htmlspecialchars($gateway['module']);
            $selected = ($gatewayValue === $storedValue) ? 'selected' : ''; // Check if this gateway is stored
            $options .= '<option value="' . $gatewayValue . '" ' . $selected . '>' . $gatewayName . '</option>';
        }
    } else {
        $options = '<option value="">Error fetching gateways</option>';
    }
    return $options;
}


