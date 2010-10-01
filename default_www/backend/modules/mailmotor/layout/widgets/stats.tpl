<div class="box" id="widgetMailmotorClassic">
	<div class="heading">
		<h3>
			<a href="{$var|geturl:'index':'mailmotor'}">
				{$lblMailmotor|ucfirst}
			</a>
		</h3>
	</div>

	<div class="options">
		<div id="tabs" class="tabs">
			<ul>
				<li><a href="#tabMailmotorSubscriptions">{$lblMailmotorSubscriptions|ucfirst}</a></li>
				<li><a href="#tabMailmotorUnsubscriptions">{$lblMailmotorUnsubscriptions|ucfirst}</a></li>
				<li><a href="#tabMailmotorStatistics">{$lblMailmotorStatistics|ucfirst}</a></li>
			</ul>

			<div id="tabMailmotorSubscriptions">
				{* All the subscriptions *}
				<div class="datagridHolder" id="datagridSubscriptions">
					{option:dgMailmotorSubscriptions}
						{$dgMailmotorSubscriptions}
					{/option:dgMailmotorSubscriptions}

					{option:!dgMailmotorSubscriptions}
						<table border="0" cellspacing="0" cellpadding="0" class="datagrid">
							<tr>
								<td>{$msgMailmotorNoSubscriptions|ucfirst}</td>
							</tr>
						</table>
					{/option:!dgMailmotorSubscriptions}
				</div>
			</div>

			<div id="tabMailmotorUnsubscriptions">
				{* All the unsubscriptions *}
				<div class="datagridHolder" id="datagridUnsubscriptions">
					{option:dgMailmotorUnsubscriptions}
						{$dgMailmotorUnsubscriptions}
					{/option:dgMailmotorUnsubscriptions}

					{option:!dgMailmotorUnsubscriptions}
						<table border="0" cellspacing="0" cellpadding="0" class="datagrid">
							<tr>
								<td>{$msgMailmotorNoUnsubscriptions|ucfirst}</td>
							</tr>
						</table>
					{/option:!dgMailmotorUnsubscriptions}
				</div>
			</div>

			<div id="tabMailmotorStatistics">
				{* All the unsubscriptions *}
				<div class="datagridHolder" id="datagridStatistics">
					{option:dgMailmotorStatistics}
						{$dgMailmotorStatistics}
					{/option:dgMailmotorStatistics}

					{option:!dgMailmotorStatistics}
						<table border="0" cellspacing="0" cellpadding="0" class="datagrid">
							<tr>
								<td>{$msgMailmotorNoSentMailings|ucfirst}</td>
							</tr>
						</table>
					{/option:!dgMailmotorStatistics}
				</div>
			</div>
		</div>
	</div>
	<div class="footer">
		<div class="buttonHolderRight">
			<a href="{$var|geturl:'addresses':'mailmotor'}" class="button"><span>{$msgMailmotorAllAddresses|ucfirst}</span></a>
		</div>
	</div>
</div>