{foreach item=action from=$actions}
{assign var=last value=$action.label}
{/foreach}
{foreach key=k item=action from=$actions}

{$action.open}

{if $k=="view"}
<img src="{$theme_dir}/Utils_GenericBrowser__view.gif" border="0" width="14" height="14">
{else}
{if $k=="delete"}
<img src="{$theme_dir}/Utils_GenericBrowser__delete.gif" border="0" width="14" height="14">
{else}
{if $k=="edit"}
<img src="{$theme_dir}/Utils_GenericBrowser__edit.gif" border="0" width="14" height="14">
{else}
{if $k=="info"}
<img src="{$theme_dir}/Utils_GenericBrowser__info.gif" border="0" width="14" height="14">
{else}
{if $k=="restore"}
<img src="{$theme_dir}/Utils_GenericBrowser__restore.gif" border="0" width="14" height="14">
{else}
{if $k=="append data"}
<img src="{$theme_dir}/Utils_GenericBrowser__append-data.gif" border="0" width="14" height="14">
{else}
{$action.label}
{/if}		
{/if}		
{/if}		
{/if}		
{/if}		
{/if}		

{$action.close}

{if $last!=$action.label}{/if}
{/foreach}