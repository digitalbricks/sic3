listenInactiveSitesToogle();
listenDarkmodeToggle();
checkDarkmode();
listenRevealPasswordToggle();
listenSiteDelete();
listenUserDelete();
checkForUpdates();


/**
 * Function for toggling inactive sites accordion-like
 * when clicking on card header.
 */
function listenInactiveSitesToogle() {
    let inactiveSitesHeader = document.querySelector('.inactivesites__header');
    if(inactiveSitesHeader) {
        inactiveSitesHeader.addEventListener("click", function(event){
            event.preventDefault()
            inactiveSitesHeader.classList.toggle('active');
        });
    }
}

/**
 * Function for watching darkmode toggle switch toggling darkmode
 */
function listenDarkmodeToggle() {
    let darkmodeToggle = document.querySelector('.darkmode-toggle');
    let root = document.documentElement;
    if(darkmodeToggle) {
        darkmodeToggle.addEventListener("click", function(event){
            event.preventDefault()
            darkmodeToggle.classList.toggle('active');
            console.log('darkmodeToggle');

            if(!window.darkMode || window.darkMode === false){
                window.darkMode = true;
                root.classList.add('darkmode');
                document.cookie = "darkmode=true;path=/";
            } else{
                window.darkMode = false;
                root.classList.remove('darkmode');
                document.cookie = "darkmode=false;path=/";
            }
        });
    }
}


/**
 * Function for checking if user prefers darkmode
 * @returns {boolean}
 */
function userPrefersDarkmode() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
}


/**
 * Function for checking if darkmode is set on page load
 * NOTE: If no cookie is set, the function checks if the user
 * prefers darkmode and reacts accordingly.
 * As soon as the user toggles darkmode, a cookie is set and
 * than the user preference is ignored.
 */
function checkDarkmode() {
    let darkmode = getCookie('darkmode');
    // if no cookie set, check if user prefers darkmode
    if(darkmode === undefined){
        darkmode = userPrefersDarkmode();
    }
    let root = document.documentElement;
    if(darkmode === true){
        window.darkMode = true;
        root.classList.add('darkmode');
    }
}

/**
 * Function for getting cookie value
 * Source: https://stackoverflow.com/questions/10730362/get-cookie-by-name
 * @param name
 * @returns {string}
 */
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
}

/**
 * Function for toggling password / site secret visibility.
 * Used on Manage Sites page.
 */
function listenRevealPasswordToggle() {
    document.querySelectorAll('.pwreveal__btn').forEach((item) => {
        item.addEventListener('click', (event) => {
            inputfield = item.closest('.pwreveal').querySelector('.pwreveal__input')
            if(inputfield.type === 'password'){
                inputfield.type = 'text';
            } else {
                inputfield.type = 'password';
            }
        });
    });
}


/**
 * Listening for clicks on site delete buttons
 */
function listenSiteDelete(){
    document.querySelectorAll('.siteactions__deleteform').forEach((item) => {
        item.addEventListener("submit", function(evt) {
            evt.preventDefault();
            let  url = item.action;
            let formdata = new FormData(item);
            let headline = 'Delete site';
            let entityname = formdata.get("siteName");
            let message = 'Are you sure you want to delete the site <strong>'+ entityname +'</strong>? <br>This action cannot be undone.';
            uiKitCornfirmDeleteDialog(headline, entityname, message, url, formdata);
        }, true);
    });
}

/**
 * Listening for clicks on user delete buttons
 */
function listenUserDelete(){
    document.querySelectorAll('.useractions__deleteform').forEach((item) => {
        item.addEventListener("submit", function(evt) {
            evt.preventDefault();
            let  url = item.action;
            let formdata = new FormData(item);
            let headline = 'Delete user';
            let entityname = formdata.get("userName");
            let message = 'Are you sure you want to delete the user <strong>'+ entityname +'</strong>? <br>This action cannot be undone.';
            uiKitCornfirmDeleteDialog(headline, entityname, message, url, formdata);
        }, true);
    });
}

/**
 * Helper function to create a UIkit confirm dialog
 * with custom headline, message and url to send the
 * formdata to (on confirmation).
 */
function uiKitCornfirmDeleteDialog(headline, entityname, message, url, formdata){
    let entitynameMarkup = '';
    if(entityname !="" && entityname != null){
        entitynameMarkup = '<br><strong>' + entityname + '</strong>';
    }
    let confirmMessage = '<h2>' + headline + entitynameMarkup +'</h2>';
    confirmMessage += '<div class="uk-alert-danger" uk-alert>\n' +
        '    <p class="uk-text-large">' + message + '</p>\n' +
        '</div>';
    UIkit.modal.confirm(confirmMessage,{i18n: {ok: 'Delete'}}).then(function() {
        const request = new XMLHttpRequest();
        request.open("POST", url);
        formdata.append('confirmed', 'true');
        request.send(formdata);
        // just a little delay to make sure the request is sent
        setTimeout(function(){location.reload();}, 500);
    }, function () {
        console.log('Rejected.')
    });
}

/**
 * Helper for creating a UIkit notification
 * in an uniform way.
 */
function notify(
    status='success',
    message='message',
    pos='top-right',
    timeout=5000
){
    UIkit.notification({
        message: message,
        status: status,
        pos: pos,
        timeout: timeout
    });
}

/**
 * Helper for copying a string to the clipboard
 * (used on /satgen/@siteId page)
 */
function copyToClipboard(id) {
    let textarea = document.getElementById(id);
    let text = textarea.textContent;

    // fallback for browsers that do not support clipboard API
    // or if no secure origin (https) is used
    if(navigator.clipboard == undefined){
        textarea.select();
        textarea.setSelectionRange(0, 99999);
        document.execCommand("copy");
        notify('success', 'Copied to clipboard');
        return;
    }
    // use the newer clipboard API if available
    navigator.clipboard.writeText(text).then(
        () => {
            notify('success', 'Copied to clipboard');
        },
        () => {
            notify('danger', 'Copy to clipboard failed');
        }
    );
}

/**
 * Function to check for updates on page load
 * and show a notification if a new version is available.
 * The notification is only shown once per session.
 */
function checkForUpdates(){
    addUpdateNote();
    let updateNotificationShown = sessionStorage['updateNotificationShown'];
    let request = new XMLHttpRequest();
    request.open("GET", "/update/check");
    request.send();
    request.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            let response = JSON.parse(this.responseText);

            // show notification if new version is available and not already shown
            if(response.updateAvailable && updateNotificationShown != 'true'){
                let message = '<strong>A new version of SIC is available!</strong><br>';
                message += 'Version: <strong>' + response.latestVersion + '</strong> is available on GitHub.<br>';
                message += 'Your current version: ' + response.installedVersion + '<br>';
                notify(
                    'sic-update',
                    message,
                    'bottom-left',
                    10000
                );
                sessionStorage['updateNotificationShown'] = 'true';
            }

            // store latest version and update url in session storage
            sessionStorage['updateLatestVersionAvailable'] = response.latestVersion;
            sessionStorage['updateUrl'] = response.updateUrl;
        }
    };
}

/**
 * Function to add the update notification to the UI (_base.html),
 * updating the HTML of .update-notification.
 */
function addUpdateNote(){
    let updateNotification = document.querySelector('.update-info');
    let latestVersion = sessionStorage['updateLatestVersionAvailable'];
    let updateUrl = sessionStorage['updateUrl'];
    if(updateNotification && latestVersion){
        let message = '<a href="' + updateUrl + '" target="_blank">SIC <strong>' + latestVersion + '</strong> available.</span></a>';
        updateNotification.innerHTML = message;
    }
}
