<style type="text/css">
.pconf h1 {
	background-color: yellow;
	margin-top: 8px;
	margin-bottom: 3px;
	padding-top: 5px;
	padding-bottom: 5px;
	margin-top: 1px;
	margin-bottom: 1px;
	padding-left: 5px;
}

.pconf h2 {
	background-color: #EEEEEE;
	padding-top: 5px;
	padding-bottom: 5px;
	margin-top: 1px;
	margin-bottom: 1px;
	padding-left: 5px;
}

.pconf .content TD {
 background-color: #F0F0F0;

}

.pChanged {
	background-color: red;
}

</style>

<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width=100% colspan="5" class="contentHead"><img src="{{ skins_url }}/images/nav.gif" hspace="8"><a href="?mod=perm">���������� ������� �������</a></td>
</tr>
</table>

������ ����������� ���������:
<div class="pconf">
<table class="content">
<tr><td>������</td><td>ID</td><td>����������</td><td>������ ��������</td><td>����� ��������</td></tr>
{% for entry in updateList %}
<tr><td>{{ GRP[entry.group]['title'] }}</td><td>{{ entry.id }}</td><td>{{ entry.title }}</td><td>{% if (entry.old == -1) %}--{% else %}{% if (entry.old == 0) %}���{% else %}��{% endif %}{% endif %}</td><td>{% if (entry.new == -1) %}--{% else %}{% if (entry.new == 0) %}���{% else %}��{% endif %}{% endif %}</td></tr>
{% endfor %}
</table>
<table border="0" width="100%" cellpadding="0" cellspacing="0"><tr><td width=100% colspan="5" class="contentHead">&nbsp;</td></tr></table>
<div style="background-color: yellow; padding: 10px;">��������� ����������: {% if (execResult) %}<b>�������</b>{% else %}<font color="red"><b>������</b></font>{% endif %}</div>
</div>
