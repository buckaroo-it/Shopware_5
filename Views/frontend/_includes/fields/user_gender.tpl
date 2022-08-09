<div class="buckaroo-input-wrapper">
    <label
        class="buckaroo-label"
        for="buckaroo-extra-fields-{$name}-user-gender">
        {s name="PluginsUserGender" namespace="frontend/buckaroo/plugins"}Gender{/s}
    </label>
    <br />
    <div class="select-field">
        <select name="buckaroo-extra-fields[{$name}][user][buckaroo_user_gender]"
                id="buckaroo-extra-fields-{$name}-user-gender"
                aria-required="true"
                class="buckaroo_auto_submit select--gender is--required{if $error_flags.buckaroo_user_gender} has--error{/if}">
            <option value="male" {if $buckarooExtraFields.user.salutation eq 'mr'} selected {/if}>{s name="PluginsUserGenderMale"}He/Him{/s}</option>
            <option value="female" {if $buckarooExtraFields.user.salutation eq 'ms'} selected {/if}>{s name="PluginsUserGenderFemale"}She/Her{/s}</option>
            <option value="unknown">{s name="PluginsUserGenderThey"}They/Them{/s}</option>
            <option value="unknown">{s name="PluginsUserGenderUnknown"}I prefer not to say{/s}</option>
        </select>
    </div>
</div>
