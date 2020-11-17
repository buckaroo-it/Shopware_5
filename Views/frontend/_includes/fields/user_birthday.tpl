<div style="width: 100%; margin-bottom: 10px;">
	<label
		style="line-height: 40px; display: block;"
		for="buckaroo-extra-fields-{$name}-user-birthday"
	>
		{s name="PluginsUserBirthday" namespace="frontend/buckaroo/plugins"}Date of birth{/s}
	</label>

	<div class="select-field" style="max-width: 72px">
		<select
			id="buckaroo-extra-fields-{$name}-user-birthday-day"
			name="buckaroo-extra-fields[{$name}][user][birthday][day]"
			class="buckaroo_auto_submit"
		>
	        {for $day = 1 to 31}
	            <option value="{$day}" {if $day == $buckarooExtraFields.user.birthday.day}selected{/if}>{$day}</option>
	        {/for}
		</select>
	</div>

	<div class="select-field" style="max-width: 72px">
		<select
			id="buckaroo-extra-fields-{$name}-user-birthday-month"
			name="buckaroo-extra-fields[{$name}][user][birthday][month]"
			class="buckaroo_auto_submit"
		>
	        {for $month = 1 to 12}
	            <option value="{$month}" {if $month == $buckarooExtraFields.user.birthday.month}selected{/if}>{$month}</option>
	        {/for}
		</select>
	</div>

	<div class="select-field" style="max-width: 90px">
		<select
			id="buckaroo-extra-fields-{$name}-user-birthday-year"
			name="buckaroo-extra-fields[{$name}][user][birthday][year]"
			class="buckaroo_auto_submit"
		>
	        {for $year = date("Y")-18 to date("Y")-120 step=-1}
	            <option value="{$year}" {if $year == $buckarooExtraFields.user.birthday.year}selected{/if}>{$year}</option>
	        {/for}
		</select>
	</div>

</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="birthday"}
</div>