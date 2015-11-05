<?php

/**
 *	firewall zone mapping.php
 *	list all firewall zone mappings
 ***************************************/

# initialize classes
$Database 	= new Database_PDO;
$Subnets 	= new Subnets ($Database);
$Result 	= new Result ();
$Zones 		= new FirewallZones($Database);

# validate session parameters
$User->check_user_session();

# fetch all zone mappings
$firewallZoneMapping = $Zones->get_zone_mappings();

# reorder by device
if ($firewallZoneMapping!==false) {
	# devices
	$devices = array();
	# add
	foreach ($firewallZoneMapping as $m) {
		$devices[$m->deviceId][] = $m;
	}
}

# display a link to the firewall zone management admin site
print '<div class="text"><a href="'.create_link('administration','firewall-zones').'"><i class="fa fa-fire"></i></a><a href="'.create_link('administration','firewall-zones').'">'._(' Firewall Zones').'</a><br><span class="text-muted" style="margin-bottom:25px;">'._('Firewall zone management').'</span></div>';


if($firewallZoneMapping) {
?>
	<!-- table -->
	<table id="mappingsPrint" class="table table-condensed">
	<?php
	# loop
	foreach ($devices as $k=>$firewallZoneMapping) { ?>
		<!-- header -->
		<tr>
		<?php
			if( strlen($devices[$k][0]->deviceDescription) < 1 )	{	print '<th colspan="10"><h4>'.$devices[$k][0]->deviceName											.'</h4></th>'; 	}
			else 													{	print '<th colspan="10"><h4>'.$devices[$k][0]->deviceName.' ( '.$devices[$k][0]->deviceDescription	.' )</h4></th>';}
		?>
		</tr>
		<tr>
			<!-- header -->
			<th><?php print _('Type'); ?></th>
			<th><?php print _('Zone'); ?></th>
			<th><?php print _('Alias'); ?></th>
			<th><?php print _('Description'); ?></th>
			<th><?php print _('Interface'); ?></th>
			<th colspan="2"><?php print _('Subnet'); ?></th>
			<th colspan="2"><?php print _('VLAN'); ?></th>
		</tr>
		<?php
		# mappings
		foreach ($firewallZoneMapping as $mapping ) {
			# set rowspan in case if there are more than one networks bound to the zone
			$counter = count($mapping->network);
			if ($counter === 0) {
				$counter = 1;
			}
			# set the loop counter
			$i = 1;
			if ($mapping->network) {
				foreach ($mapping->network as $key => $network) {
					print '<tr>';
					if ($i === 1) {
						print '<td rowspan="'.$counter.'">';
					# columns
					if ($mapping->indicator == 0 ) {
						print '<span class="fa fa-home"  title="'._('Own Zone').'"></span>';
					} else {
						print '<span class="fa fa-group" title="'._('Customer Zone').'"></span>';
					}
					print '</td>';
					print '<td rowspan="'.$counter.'">'.$mapping->zone.'</td>';
					print '<td rowspan="'.$counter.'">'.$mapping->alias.'</td>';
					print '<td rowspan="'.$counter.'">'.$mapping->description.'</td>';
					print '<td rowspan="'.$counter.'">'.$mapping->interface.'</td>';
					}
					# display subnet informations
					if ($network->subnetId) {
						if (!$network->subnetIsFolder) {
							if ($network->subnetDescription) {
								print '<td><a href="'.create_link("subnets",$network->sectionId,$network->subnetId).'">'.$Subnets->transform_to_dotted($network->subnet).'/'.$network->subnetMask.'</a></td>';
								print '<td><a href="'.create_link("subnets",$network->sectionId,$network->subnetId).'">'.$network->subnetDescription.'</a></td>';
							} else {
								print '<td colspan="2"><a href="'.create_link("subnets",$network->sectionId,$network->subnetId).'">'.$Subnets->transform_to_dotted($network->subnet).'/'.$network->subnetMask.'</a></td>';
							}
						} else {
							print '<td><a href="'.create_link("subnets",$network->sectionId,$network->subnetId).'">Folder</a></td>';
							print '<td><a href="'.create_link("subnets",$network->sectionId,$network->subnetId).'">'.$network->subnetDescription.'</a></td>';
						}
					} else {
						print '<td colspan="2"></td>';
					}
					# display vlan informations
					if ($network->vlanId) {
						print '<td><a href="'.create_link('tools','vlan',$network->domainId,$network->vlanId).'">'.$network->vlan.'</a></td>';
						print '<td><a href="'.create_link('tools','vlan',$network->domainId,$network->vlanId).'">'.$network->vlanName.'</a></td>';
					} else {
						print '<td colspan="2"></td>';
					}
					print '</tr>';
					# increase the loop counter
					$i++;
					}
				} else {
					# display only the zone mapping data if there is no network data available
					print '<td rowspan="'.$counter.'">';
					# columns
					if ($mapping->indicator == 0 ) {
						print '<span class="fa fa-home"  title="'._('Own Zone').'"></span>';
					} else {
						print '<span class="fa fa-group" title="'._('Customer Zone').'"></span>';
					}
					print '<td rowspan="'.$counter.'">'.$mapping->zone.'</td>';
					print '<td rowspan="'.$counter.'">'.$mapping->alias.'</td>';
					print '<td rowspan="'.$counter.'">'.$mapping->description.'</td>';
					print '<td rowspan="'.$counter.'">'.$mapping->interface.'</td>';
					print '<td colspan="4">';
					print '</tr>';
				}
		}
	}
	print '</table>';
}
else {
	# print an info if there are no zones in the database
	$Result->show("info", _("No firewall zones configured"), false);
}
?>