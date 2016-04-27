{literal}
<style type="text/css">
  .info, .warn {
    border-radius: 4px;
    color: #3e3e3e;
    font-size: 12px;
    margin: 0 0 8px;
    padding: 4px;
  }
  .info {
    background-color: #F1F8EB;
    border: 1px solid #B0D730;
  }
  .warn {
    background-color: #F8EBEB;
    border: 1px solid red;
  }
  .crm-container fieldset {
    border-top: none;
    padding: 0px;
  }
</style>
{/literal}

<div class="crm-block crm-form-block">

  <div class="info">
    <h1>{ts}SendGrid Event Notification Processor Configuration{/ts}</h1>
    <fieldset>
       <table class="form-layout">
        <tr>
          <td class="label">{$form.secretcode.label}</td>
          <td>{$form.secretcode.html}<br />
            <span class="description">{ts}You may provide a secret code here and in the notification URL in order to discourage spoof event notifications.{/ts}</span>
          </td>
        </tr>
        <tr>
          <td class="label">{$form.open_click_processor.label}</td>
          <td>{$form.open_click_processor.html}<br />
            <span class="description">{ts}Select where open and click-throughs should be processed. Either way, the same data is collected, stored, and reported.{/ts}</span>
          </td>
        </tr>
        <tr>
          <td></td>
          <td>{$form.track_optional.html}</td>
        </tr>
        <tr>
          <td></td>
          <td><div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl"}</div></td>
        </tr>
       </table>
    </fieldset>
  </div>

  <div class="info">
    <h1>{ts}SendGrid Event Notification Configuration{/ts}</h1>
    {*<p>{ts}We should probably put a link here to the event notification setup screen on SendGrid.{/ts}</p>*}
    <p>Based on the secret code provided above, your <em>HTTP Post URL</em> is...</p>
    <pre>{$url}</pre>
    <p>{ts}While it is safe to select all actions to be reported by the SendGrid Event Notification app,
    for better performance <em>Processed</em>, <em>ASM Group Unsubscribe</em>, and <em>ASM Group Resubscribe</em>
    should be deselected. They are essentially meaningless and therefore ignored. CiviCRM already counts as
    delivered as soon as the mail is sent, so <em>Delivered</em> is also ignored. <em>Deferred</em> is simply
    a temporary failure that will be reattempted; this extension does nothing more that record it to the main
    CiviCRM log, so you may wish to deselect this action as well.{/ts}</p>
  </div>

</div>
