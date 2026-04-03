@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ config('app.url') . '/logo-login.png' }}" class="logo" alt="{{ config('app.name') }} Logo" style="max-height: 80px;">
</a>
</td>
</tr>
