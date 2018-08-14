<?php

/**
 * Script to display customer details
 *
 */

# verify that user is logged in
$User->check_user_session();
# verify module permissions
$User->check_module_permissions ("customers", 1, true);


print "<h4>"._('Customer details')." - $customer->title</h4>";
print "<hr>";
print "<br>";

// back
print "<a class='btn btn-sm btn-default' href='".create_link($_GET['page'], "customers")."'><i class='fa fa-angle-left'></i> "._("All customers")."</a><br><br>";

# circuit
print "<table class='ipaddress_subnet table-condensed table-auto'>";

	// title
	print '<tr>';
	print "	<td colspan='2' style='font-size:18px'><strong>$customer->title</strong></td>";
	print "</tr>";

	// address
	print "<tr>";
	print "	<td colspan='2'><hr></td>";
	print "</tr>";

	print '<tr>';
	print "	<th>". _('Address').'</th>';
	print "	<td>$customer->address<br>$customer->postcode<br>$customer->city <br>$customer->state</td>";
	print "</tr>";

	// contact
	print "<tr>";
	print "	<td colspan='2'><hr></td>";
	print "</tr>";

	print '<tr>';
	print "	<td></td>";
	print "</tr>";

	print '<tr>';
	print "	<th class='text-right'>";
	print _("Contact details")."<br>";
	print "		<i class='fa fa-user'></i><br>";
	print "		<i class='fa fa-at'></i><br>";
	print "		<i class='fa fa-phone'></i>";
	print " </th>";
	print "	<td><br>";
	print $customer->contact_person."<br>";
	print $customer->contact_mail."<br>";
	print $customer->contact_phone."<br>";
	print "</td>";
	print "</tr>";

	if(sizeof($custom_fields) > 0) {

    	print "<tr>";
    	print "	<td colspan='2'><hr></td>";
    	print "</tr>";

		foreach($custom_fields as $field) {

			# fix for boolean
			if($field['type']=="tinyint(1)" || $field['type']=="boolean") {
				if($customer->{$field['name']}=="0")		{ $customer->{$field['name']} = "false"; }
				elseif($customer->{$field['name']}=="1")	{ $customer->{$field['name']} = "true"; }
				else									{ $customer->{$field['name']} = ""; }
			}

			# create links
			$customer->{$field['name']} = $Result->create_links ($customer->{$field['name']});

			print "<tr>";
			print "<th>".$Tools->print_custom_field_name ($field['name'])."</th>";
			print "<td>".$customer->{$field['name']}."</d>";
			print "</tr>";
		}
	}

	// edit, delete
	if($User->is_admin(false) || $User->user->editCircuits=="Yes") {
		print "<tr>";
		print "	<td colspan='2'><hr></td>";
		print "</tr>";

    	print "<tr>";
    	print "	<td></td>";
		print "	<td class='actions'>";
		print "	<div class='btn-group'>";
		if($User->get_module_permissions("customers")>=2)
		print "		<a class='btn btn-xs btn-default open_popup' data-script='app/admin/customers/edit.php' data-class='700' data-action='edit' data-id='$customer->id'><i class='fa fa-pencil'></i></a>";
		if($User->get_module_permissions("customers")==3)
		print "		<a class='btn btn-xs btn-default open_popup' data-script='app/admin/customers/edit.php' data-class='700' data-action='delete' data-id='$customer->id'><i class='fa fa-times'></i></a>";
		print "	</div>";
		print " </td>";
    	print "</tr>";
	}

print "</table>";