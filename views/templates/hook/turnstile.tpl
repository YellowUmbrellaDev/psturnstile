{if !empty($psturnstile_site_key)}
<div class="psturnstile-widget cf-turnstile"
     data-sitekey="{$psturnstile_site_key|escape:'html':'UTF-8'}"
     data-theme="{$psturnstile_theme|default:'auto'|escape:'html':'UTF-8'}"
     data-size="{$psturnstile_size|default:'normal'|escape:'html':'UTF-8'}"
     data-response-field-name="cf-turnstile-response"></div>
{/if}
