<div class="buckaroo-input-wrapper">

    <input 
    type="text"
    id="buckaroo-extra-fields-{$name}-user-buckaroo_encrypted_data"
    name="buckaroo-extra-fields[{$name}][user][buckaroo_encrypted_data]"
	
    class="encryptedCardData buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_encrypted_data']} buckaroo-has-error{/if}" 
    hidden />

</div>

<br>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_encrypted_data"}
</div>