function clear_accesstoken(error_message) {
    document.getElementById("bean_inst_access_token").value = "";
    document.getElementById("bean_inst_plugin_userid").value = "";

    if (document.getElementById("bean_inst_access_token_status")) {
        document.getElementById("bean_inst_access_token_status").style.color = "red";
        document.getElementById("bean_inst_access_token_status").innerHTML = error_message;
    }
}

function bean_instagram_get_access_token(redirect_uri) {
    var clientid = document.forms[0].elements['bean_inst_client_id'].value;

    if (clientid.length == 0) {
        document.forms[0].elements['bean_inst_client_id'].focus();

        if (!document.getElementById("bean_inst_client_id_error")) {
            var pNode = document.createElement("strong");
            pNode.innerHTML = "Please fill in the \"Client ID\" field above first!";
            pNode.setAttribute('id', 'bean_inst_client_id_error');
            pNode.style.paddingLeft = "10px";
            pNode.style.lineHeight = "28px";
            this.parentNode.appendChild(pNode);
        }

        return;
    }

    var form = document.createElement("form");
    form.setAttribute('id', 'instagram_auth');
    form.setAttribute('name', 'instagram_auth');
    form.setAttribute('action', 'https://api.instagram.com/oauth/authorize/');
    form.setAttribute('method', 'GET');

    var responseType = document.createElement('input');
    responseType.setAttribute('type', 'hidden');
    responseType.setAttribute('name', 'response_type');
    responseType.setAttribute('value', 'code');
    responseType.setAttribute('id', 'instagram_auth_response_type');

    var redirectURI = document.createElement('input');
    redirectURI.setAttribute('type', 'hidden');
    redirectURI.setAttribute('name', 'redirect_uri');
    redirectURI.setAttribute('value', redirect_uri);
    redirectURI.setAttribute('id', 'instagram_auth_redirect_uri');

    var clientID = document.createElement('input');
    clientID.setAttribute('type', 'hidden');
    clientID.setAttribute('name', 'client_id');
    clientID.setAttribute('value', clientid);
    clientID.setAttribute('id', 'instagram_auth_client_id');

    form.appendChild(responseType);
    form.appendChild(redirectURI);
    form.appendChild(clientID);

    form.submit();

    event.preventDefault();
    return false;
}