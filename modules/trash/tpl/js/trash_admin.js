/**
 * @file   modules/trash/js/trash_admin.js
 * @author Arnia (dev@karybu.org)
 * @brief  trash Modules for managers javascript
 **/


/* After emptying the Recycle Bin */
function completeEmptyTrash(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];

	alert(message);
	if(error == '0') window.location.reload();
}

function goRestore(trash_srl)
{
	if(confirm(confirm_restore_msg))
	{
		var params = {'trash_srl':trash_srl};
		exec_xml('admin', 'procTrashAdminRestore', params, completeRestore);
	}
}

function completeRestore(ret_obj)
{
    var error = ret_obj['error'];
    var message = ret_obj['message'];

	alert(message);
	if(error == '0') window.location.reload();
}

function getTrashList()
{
	var trashListTable = jQuery('#trashListTable');
	var cartList = [];
	trashListTable.find(':checkbox[name=cart]').each(function(){
		if(this.checked) cartList.push(this.value); 
	});

    var params = new Array();
    var response_tags = ['error','message', 'trash_list'];
	params["trash_srls"] = cartList.join(",");

    exec_xml('trash','procTrashAdminGetList',params, completeGetTrashList, response_tags);
}

function completeGetTrashList(ret_obj, response_tags)
{
	var htmlListBuffer = '';

	if(ret_obj['trash_list'] == null)
	{
		htmlListBuffer = '<tr>' +
							'<td colspan="3" style="text-align:center;">'+ret_obj['message']+'</td>' +
						'</tr>';
	}
	else
	{
		var trash_list = ret_obj['trash_list']['item'];

		if(!jQuery.isArray(trash_list)) trash_list = [trash_list];
		for(var x in trash_list)
		{
			var objTrash = trash_list[x];
			var title = '';
			if(objTrash.title == '') title = no_text_comment;
			else title = objTrash.title;

			htmlListBuffer += '<tr>' +
								'<td class="title">'+ title +'</td>' +
								'<td class="nowr">'+ objTrash.nickName +'</td>' +
								'<td class="nowr">'+ objTrash.ipaddress +'</td>' +
							'</tr>' +
							'<input type="hidden" name="cart[]" value="'+objTrash.trashSrl+'" />';
		}
		jQuery('#selectedTrashCount').html(trash_list.length);
	}
	jQuery('#trashManageListTable>tbody').html(htmlListBuffer);
}
