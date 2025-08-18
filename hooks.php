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


/**
 * Hook 1: Remove/Unset Restricted Payment Gateway on Invoice View
 */


add_hook('ClientAreaPage', 99999, function ($vars) {
    if ($vars['filename'] === 'viewinvoice' && isset($_GET['id'])) {
        $theme = isset($vars['template']) ? $vars['template'] : '';

        // Check if the session UID exists in ws_bypass_gateway_client table
        if (isset($_SESSION['uid'])) {
            $sessionUid = $_SESSION['uid'];
            $existsInTable = Capsule::table('ws_bypass_gateway_client')
                ->where('client_id', $sessionUid)
                ->exists();

            // If session UID exists, do not modify gateways
            if ($existsInTable) {
                return $vars;
            }
        }

        // Fetch the renewal restriction setting from the database
        $storedRenewalRestrict = Capsule::table('tbladdonmodules')
            ->where('module', 'gateway_control')
            ->where('setting', 'renewal_restrict')
            ->value('value');

        if ($storedRenewalRestrict) {
            $invoiceId = (int) $_GET['id'];

            // Check if the invoice is for renewal (Hosting or Domain)
            $isRenewal = Capsule::table('tblinvoiceitems')
                ->where('invoiceid', $invoiceId)
                ->where(function ($query) {
                    $query->where('type', 'Hosting') // Service renewals
                          ->orWhere('type', 'Domain'); // Domain renewals
                })
                ->exists();

            if ($isRenewal) {
                // Remove the restricted gateway from available options
                foreach ($vars['availableGateways'] as $key => $method) {
                    if ($key == $storedRenewalRestrict) {
                        unset($vars['availableGateways'][$key]);
                    }
                }

                // If using Lagom2, also remove the gateway from the HTML dropdown
                if ($theme === 'lagom2' && !empty($vars['gatewaydropdown'])) {
                    $pattern = '/<option[^>]*value=["\']?' . preg_quote($storedRenewalRestrict, '/') . '["\']?[^>]*>.*?<\/option>/i';
                    $filteredGatewayDropdown = preg_replace($pattern, '', $vars['gatewaydropdown']);
                    logActivity('Gateway Control: Renewal Restriction Applied in lagom theme');                    
                    return array_merge($vars, [
                        'isRenewal' => true,
                        'restrictedGateway' => $storedRenewalRestrict,
                        'gatewaydropdown' => $filteredGatewayDropdown
                    ]);
                } else {
                    logActivity('Gateway Control: Renewal Restriction not applied in ' . $theme);
                }

                return array_merge($vars, [
                    'isRenewal' => true,
                    'restrictedGateway' => $storedRenewalRestrict
                ]);
            }
        }
    }

    return $vars;
});

// Inject JavaScript only if renewal restriction applies
add_hook('ClientAreaHeadOutput', 99999, function ($vars) {
    if ($vars['filename'] === 'viewinvoice' && !empty($vars['isRenewal']) && !empty($vars['restrictedGateway'])) {
        $restrictedGateway = $vars['restrictedGateway'];
        return <<<HTML
<script>
document.addEventListener("DOMContentLoaded", function() {
    let select = document.querySelector('select[name="gateway"]');
    if (select) {
        let optionToRemove = select.querySelector('option[value="{$restrictedGateway}"]');
        if (optionToRemove) {
            optionToRemove.remove();
        }
    }
});
</script>
HTML;
    }
});






/**
 * Hook 2: Update Payment Method Immediately After Invoice Creation
 */
add_hook('InvoiceCreation', 1, function ($vars) {
    $invoiceId = $vars['invoiceid'];
    // Fetch the client ID associated with the invoice
    $clientId = Capsule::table('tblinvoices')
        ->where('id', $invoiceId)
        ->value('userid');

    // Check if the client ID exists in the ws_bypass_gateway_client table
    if ($clientId) {
        $existsInTable = Capsule::table('ws_bypass_gateway_client')
            ->where('client_id', $clientId)
            ->exists();

        // If client ID exists in the table, skip the logic
        if ($existsInTable) {
            return; // Skip further processing
        }
    }

    // Fetch settings for renewal restrictions
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'gateway_control')
        ->whereIn('setting', ['renewal_restrict', 'renewal_restrict_replacement'])
        ->pluck('value', 'setting');

    $storedRenewalRestrict = $settings['renewal_restrict'] ?? null;
    $storedRenewalRestrictReplacement = $settings['renewal_restrict_replacement'] ?? null;

    if (!$storedRenewalRestrict || !$storedRenewalRestrictReplacement) {
        return;
    }

    // Check if the invoice is a renewal invoice
    $relatedIds = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->pluck('relid');

    // $isRenewal_ = !empty($relatedIds) && Capsule::table('tblhosting')
    //     ->whereIn('id', $relatedIds)
    //     ->exists();
    $isRenewal = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->where(function ($query) {
            $query->where('type', 'Hosting') // Check for service renewals
                    ->orWhere('type', 'Domain'); // Check for domain renewals
        })
        ->exists();

    if ($isRenewal) {
        // Check if the current payment method matches the restricted one
        $currentPaymentMethod = Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->value('paymentmethod');

        if ($currentPaymentMethod === $storedRenewalRestrict) {
            // Update the payment method to the replacement value
            Capsule::table('tblinvoices')
                ->where('id', $invoiceId)
                ->update(['paymentmethod' => $storedRenewalRestrictReplacement]);
        }
    }
});





