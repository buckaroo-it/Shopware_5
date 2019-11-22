<div id="buckaroo-extra-fields-{$name}-{$entity}-{$key}-errors" class="buckaroo-errors-wrapper">
	{foreach from=$buckarooValidationMessages[$name][$entity][$key] item=errorMessage}
	<div class="buckaroo-error-message">
		{$errorMessage}
	</div>
	{/foreach}
</div>
