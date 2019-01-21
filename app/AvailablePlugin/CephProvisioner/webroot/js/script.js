function keyDownload(data, filename) {
    var blob = new Blob([data], {type: 'application/octet-stream'});
    if(window.navigator.msSaveOrOpenBlob) {
        window.navigator.msSaveBlob(blob, filename);
    }
    else{
        var elem = window.document.createElement('a');
        elem.href = window.URL.createObjectURL(blob);
        elem.download = filename;        
        document.body.appendChild(elem);
        elem.click();        
        document.body.removeChild(elem);
        window.URL.revokeObjectURL(blob);
    }
}

function validateAndSubmitUser(confirmText) {
    validateElement = $('#newuser_validate');
    var user = $('#AddRgwUseridRgwNewUserid').val();
    if (user.length < 8) {
        validateElement.html('Must be at least 8 characters');
        return false;
    } else if (/[^a-zA-Z0-9\-_]/.test(user)) {
        validateElement.html('Can only contain alphanumeric, hyphen (-), or underscore (_)');
        return false;
    } else  { 
        validateElement.html('');
        js_confirm_generic(confirmText + ": " + user + " ?", 'javascript: $(\'#AddRgwUseridCredopForm\').submit()');
        return true;
    }
}

function validateAndSubmitPlacement(confirmText, user) {
    var selected = $('#rgw_placement_row_' + user).find('#ChangeBucketPlacementRgwPlacement option:selected').text()
    // selected = $('#ChangeBucketPlacementRgwPlacement option:selected').text()
    js_confirm_generic(confirmText + ": " + selected + " for userid: " + user, 'javascript: $(\'#rgw_placement_row_' + user + '\').find(\'#ChangeBucketPlacementCredopForm\').submit()');
}

/*
$(document).ready(function () {
    //formElement = 
    $('#AddRgwUseridCredopForm').on('submit',function (){ 
        validateElement = $('#newuser_validate');
        var user = $('#AddRgwUseridRgwNewUserid').val();
        if (user.length < 8) {
            validateElement.html('Must be at least 8 characters');
            return false;
        } else if (/[^a-zA-Z0-9\-_]/.test(user)) {
            validateElement.html('Can only contain alphanumeric, hyphen (-), or underscore (_)');
            return false;
        } else  { 
            validateElement.html('');
            return true;
        }
    });
});
*/

// available as hooks
//    js_local_onload();

//    js_local_onsubmit();

