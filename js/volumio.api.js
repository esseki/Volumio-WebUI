/*
 *      PlayerUI Copyright (C) 2013 Andrea Coiutti & Simone De Gregori
 *		 Tsunamp Team
 *      http://www.tsunamp.com
 *
 *  This Program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3, or (at your option)
 *  any later version.
 *
 *  This Program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with TsunAMP; see the file COPYING.  If not, see
 *  <http://www.gnu.org/licenses/>.
 *
 *  Authors:
 *  - v1, 1.1: Andrea Coiutti (aka ACX)
 *  - v1, 1.1: Simone De Gregori (aka Orion)
 *  - v2: Michelangelo Guarise
 *  - v2: Joel Takvorian
 * 
 *  file:                    volumio.api.js
 *  version:                 2
 */

 // Global GUI object
 GUI = {
    json: 0,
    cmd: 'status',
    playlist: null,
    currentsong: null,
    currentknob: null,
    state: '',
    currentpath: '',
    halt: 0,
    volume: null,
    currentDBpos: new Array(0,0,0,0,0,0,0,0,0,0,0),
    DBentry: new Array('', '', ''),
    visibility: 'visible',
    DBupdate: 0
};

// FUNZIONI
// ----------------------------------------------------------------------------------------------------

function sendCmd(inputcmd) {
    $.ajax({
        type : 'GET',
        url : 'command/?cmd=' + inputcmd,
        async : true,
        cache : false,
        success : function(data) {
            GUI.halt = 1;
        },
    });
}

function sendPLCmd(inputcmd) {
    $.ajax({
        type : 'GET',
        url : 'db/?cmd=' + inputcmd,
        async : true,
        cache : false,
        success : function(data) {
            GUI.halt = 1;
        },
    });
}

function backendRequest() {
    $.ajax({
        type : 'GET',
        url : '_player_engine.php?state=' + GUI.state,
        async : true,
        cache : false,
        success : function(data) {
            renderUI(data);
            GUI.currentsong = GUI.json['currentsong'];
            backendRequest();
        },
        error : function() {
            setTimeout(function() {
                GUI.state = 'disconnected';
                $('#loader').show();
                $('#countdown-display').countdown('pause');
                window.clearInterval(GUI.currentKnob);
                backendRequest();
            }, 5000);
        }
    });
}

function renderUI(data) {
    // update global GUI array
    GUI.json = eval('(' + data + ')');
    GUI.state = GUI.json['state'];
    updateGUI(GUI.json);
    if (GUI.state != 'disconnected') {
        $('#loader').hide();
    }
    refreshTimer(parseInt(GUI.json['elapsed']), parseInt(GUI.json['time']), GUI.json['state']);
    refreshKnob(GUI.json);
    if (GUI.json['playlist'] != GUI.playlist) {
        getPlaylist(GUI.json);
        GUI.playlist = GUI.json['playlist'];
    }
    GUI.halt = 0;
}

function getPlaylist(json){
    $.getJSON('db/?cmd=playlist', function(data) {
        // We wait for playlist to be loaded before loading the library, which is much more time-consumming
        loadLibraryIfNeeded();

        // Read received data for playlist
        var i = 0;
        var content = '';
        var output = '';
        if (data) {
            for (i = 0; i < data.length; i++){
                if (json['state'] != 'stop' && i == parseInt(json['song'])) {
                    content = '<li id="pl-' + (i + 1) + '" class="active clearfix">';
                } else {
                    content = '<li id="pl-' + (i + 1) + '" class="clearfix">';
                }
                content += '<div class="pl-action"><a class="btn" href="#notarget" title="Remove song from playlist"><i class="fa fa-remove"></i></a></div>';
                if (typeof data[i].Title != 'undefined') {
                    content += '<div class="pl-entry">';
                    content += data[i].Title + ' <em class="songtime">' + timeConvert(data[i].Time) + '</em>';
                    content += ' <span>';
                    content +=  data[i].Artist;
                    content += ' - ';
                    content +=  data[i].Album;
                    content += '</span></div></li>';
                    output = output + content;
                } else {
                    songpath = parsePath(data[i].file);
                    content += '<div class="pl-entry">';
                    content += data[i].file.replace(songpath + '/', '') + ' <em class="songtime">' + timeConvert(data[i].Time) + '</em>';
                    content += ' <span>';
                    content += ' path \: ';
                    content += songpath;
                    content += '</span></div></li>';
                    output = output + content;
                }
            }
        }
        $('ul.playlist').html(output);
    });
}

function parsePath(str) {
	var cutpos=str.lastIndexOf("/");
	//-- verify this switch! (Orion)
	if (cutpos !=-1) {
        var songpath = str.slice(0,cutpos);
	}  else {
        songpath = '';
	}
	return songpath;
}

function pluginListItem(id, text, faicon, onclick) {
    return '<li id="#' + id + '" class="db-plugin" onclick="'
        + onclick + '"><div class="db-icon db-other"><i class="fa '
        + faicon + ' icon-root sx"></i></div><div class="db-entry db-other">'
        + text + '</div></li>';
}

function parseResponse(inputArr,respType,i,inpath) {		
	switch (respType) {
		case 'playlist':		
			// code placeholder
		break;

		case 'db':
			if (inpath == '' && typeof inputArr[i].file != 'undefined') {
                inpath = parsePath(inputArr[i].file)
			}
			if (typeof inputArr[i].file != 'undefined') {
				if (typeof inputArr[i].Title != 'undefined') {
					content = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
					content += inputArr[i].file;
					content += '"><div class="db-icon db-song db-browse"><i class="fa fa-music sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu"><i class="fa fa-reorder"></i></a></div><div class="db-entry db-song db-browse">';
					content += inputArr[i].Title + ' <em class="songtime">' + timeConvert(inputArr[i].Time) + '</em>';
					content += ' <span>';
					content +=  inputArr[i].Artist;
					content += ' - ';
					content +=  inputArr[i].Album;
					content += '</span></div></li>';
					showtype = 'music'
				} else {
                    var dbItemClass = (inputArr[i].Time === undefined) ? "db-other" : "db-song";
					content = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
					content += inputArr[i].file;
					if (inpath == 'WEBRADIO') {
                        content += '"><div class="db-icon ' + dbItemClass + ' db-browse"><i class="fa fa-microphone sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu"><i class="fa fa-reorder"></i></a></div><div class="db-entry ' + dbItemClass + ' db-browse">';
                        showtype = 'radio'
					} else {
                        content += '"><div class="db-icon ' + dbItemClass + ' db-browse"><i class="fa fa-music sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu"><i class="fa fa-reorder"></i></a></div><div class="db-entry ' + dbItemClass + ' db-browse">';
                        showtype = 'file'
					}
					content += inputArr[i].file.replace(inpath + '/', '').replace('.pls', '') + ' <em class="songtime">' + timeConvert(inputArr[i].Time) + '</em>';
					content += '</div></li>';
					
				}
			} else {
				content = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
				content += inputArr[i].directory;
				showtype = 'file'
				if (inpath != '') {
					content += '"><div class="db-icon db-folder db-browse"><i class="fa fa-folder-open sx"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu"><i class="fa fa-reorder"></i></a></div><div class="db-entry db-folder db-browse">';
				} else if (inputArr[i].directory == 'WEBRADIO') {
					content += '"><div class="db-icon db-folder db-browse"><i class="fa fa-microphone icon-root sx"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-root"><i class="fa fa-reorder"></i></a></div><div class="db-entry db-folder db-browse">';
				} else if (inputArr[i].directory == 'NAS') {
					content += '"><div class="db-icon db-folder db-browse"><i class="fa fa-code-fork icon-root sx"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-root"><i class="fa fa-reorder"></i></a></div><div class="db-entry db-folder db-browse">';
				} else if (inputArr[i].directory == 'USB') {
					content += '"><div class="db-icon db-folder db-browse"><i class="fa fa-hdd-o icon-root sx"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-root"><i class="fa fa-reorder"></i></a></div><div class="db-entry db-folder db-browse">';
				} else if (inputArr[i].directory == 'RAMPLAY') {
					content += '"><div class="db-icon db-folder db-browse"><i class="fa fa-spinner icon-root sx"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-root"><i class="fa fa-reorder"></i></a></div><div class="db-entry db-folder db-browse">';	
				}	
				content += inputArr[i].directory.replace(inpath + '/', '');
				content += '</div></li>';
			}
		break;
		
	}
	return content;
} // end parseResponse()

function getDB(cmd, path, browsemode, uplevel){
	if (cmd == 'filepath') {
		$.post('db/?cmd=filepath', { 'path': path }, function(data) {
			populateDB(data, path, uplevel);
		}, 'json');
	} else if (cmd == 'add') {
		$.post('db/?cmd=add', { 'path': path }, function(path) {
		}, 'json');
	} else if (cmd == 'addplay') {
		$.post('db/?cmd=addplay', { 'path': path }, function(path) {
		}, 'json');
	} else if (cmd == 'addreplaceplay') {
		$.post('db/?cmd=addreplaceplay', { 'path': path }, function(path) {
		}, 'json');
	} else if (cmd == 'update') {
		$.post('db/?cmd=update', { 'path': path }, function(path) {
		}, 'json');
	} else if (cmd == 'search') {
		var keyword = $('#db-search-keyword').val();
		$.post('db/?querytype=' + browsemode + '&cmd=search', { 'query': keyword }, function(data) {
			populateDB(data, path, uplevel, keyword);
		}, 'json');
	} else if (cmd == 'playall') {
                $.post('db/?cmd=playall', { 'path': path }, function(data) {}, 'json');
        } else if (cmd == 'addall') {
                $.post('db/?cmd=addall', { 'path': path }, function(data) {}, 'json');
        }
}

function populateDB(data, path, uplevel, keyword){
	if (path) GUI.currentpath = path;
	var DBlist = $('ul.database');
	DBlist.html('');
	if (keyword) {
		var results = (data.length) ? data.length : '0';
		var s = (data.length == 1) ? '' : 's';
		var text = "" + results + ' result' + s + ' for "<em class="keyword">' + keyword + '</em>"';
		$("#db-back").attr("title", "Close search results and go back to the DB");
		$("#db-back-text").html(text);
		$("#db-back").show();
	} else if (path != '') {
		$("#db-back").attr("title", "");
		$("#db-back-text").html("back");
		$("#db-back").show();
	} else {
        $("#db-back").hide();
        if (library && library.isEnabled && !library.displayAsTab) {
            DBlist.append(pluginListItem("db-plug-lib", "LIBRARY", "fa-columns", "showLibraryView()"));
        }
    }
	var i = 0;
	for (i = 0; i < data.length; i++){
	 	DBlist.append(parseResponse(data,'db',i,path));
	}
	$('#db-currentpath span').html(path);
	if (uplevel) {
		$('#db-' + GUI.currentDBpos[GUI.currentDBpos[10]]).addClass('active');
		customScroll('db', GUI.currentDBpos[GUI.currentDBpos[10]]);
	} else {
		customScroll('db', 0, 0);
	}
	if (showtype == 'radio') {
        $("#webradio-add").show();
	} else {
        $("#webradio-add").hide();
	}
}

// update interface
function updateGUI(json){
    // check MPD status
    refreshState(GUI.state);
    // check song update
    if (GUI.currentsong != json['currentsong']) {
        countdownRestart(0);
        if ($('#panel-dx').hasClass('active')) {
            var current = parseInt(json['song']);
            customScroll('pl', current);
        }
    }
    // common actions

    // Don't update the knob if it's currently being changed
    var volume = $('#volume');
    if (volume[0] && (volume[0].knobEvents === undefined || !volume[0].knobEvents.isSliding)) {
        volume.val((json['volume'] == '-1') ? 100 : json['volume']).trigger('change');
    }
    $('#currentartist').html(json['currentartist']);
    $('#currentsong').html(json['currentsong']);
    $('#currentalbum').html(json['currentalbum']);
    if (json['repeat'] == 1) {
        $('#repeat').addClass('btn-primary');
    } else {
        $('#repeat').removeClass('btn-primary');
    }
    if (json['random'] == 1) {
        $('#random').addClass('btn-primary');
    } else {
        $('#random').removeClass('btn-primary');
    }
    if (json['consume'] == 1) {
        $('#consume').addClass('btn-primary');
    } else {
        $('#consume').removeClass('btn-primary');
    }
    if (json['single'] == 1) {
        $('#single').addClass('btn-primary');
    } else {
        $('#single').removeClass('btn-primary');
    }

    GUI.halt = 0;
    GUI.currentsong = json['currentsong'];
	GUI.currentartist = json['currentartist'];
	//Change Name according to Now Playing
	if (GUI.currentartist!=null && GUI.currentsong!=null) {
	document.title = json['currentsong'] + ' - ' + json['currentartist'] + ' - ' + 'Volumio';
	} else {
            document.title = 'Volumio - Audiophile Music Player';
        }
}

// update status on playback view
function refreshState(state) {
    if (state == 'play') {
        $('#play').addClass('btn-primary');
        $('#play i').removeClass('fa fa-pause').addClass('fa fa-play');
        $('#stop').removeClass('btn-primary');
    } else if (state == 'pause') {
        $('#playlist-position').html('Not playing');
        $('#play').addClass('btn-primary');
        $('#play i').removeClass('fa fa-play').addClass('fa fa-pause');
        $('#stop').removeClass('btn-primary');
    } else if (state == 'stop') {
        $('#play').removeClass('btn-primary');
        $('#play i').removeClass('fa fa-pause').addClass('fa fa-play');
        $('#stop').addClass('btn-primary');
        $('#countdown-display').countdown('destroy');
        $('#elapsed').html('00:00');
        $('#total').html('');
        $('#time').val(0).trigger('change');
        $('#format-bitrate').html('&nbsp;');
        $('.playlist li').removeClass('active');
    }
    if (state == 'play' || state == 'pause') {
        $('#elapsed').html(timeConvert(GUI.json['elapsed']));
        $('#total').html(timeConvert(GUI.json['time']));
        //$('#time').val(json['song_percent']).trigger('change');
        $('#playlist-position').html('Playlist position ' + (parseInt(GUI.json['song']) + 1) +'/'+GUI.json['playlistlength']);
        var fileinfo = (GUI.json['audio_channels'] && GUI.json['audio_sample_depth'] && GUI.json['audio_sample_rate']) ? (GUI.json['audio_channels'] + ' - ' + GUI.json['audio_sample_depth'] + ' bit - ' + GUI.json['audio_sample_rate'] +' kHz ') : '&nbsp;';
        $('#format-bitrate').html(fileinfo);
        $('.playlist li').removeClass('active');
        var current = parseInt(GUI.json['song']) + 1;
        $('.playlist li:nth-child(' + current + ')').addClass('active');
    }
	
	// show UpdateDB icon
	// console.log('dbupdate = ', GUI.json['updating_db']);
	if (typeof GUI.json['updating_db'] != 'undefined') {
		$('.open-panel-sx').html('<i class="fa fa-refresh icon-spin"></i> Updating');
	} else {
		$('.open-panel-sx').html('<i class="fa fa-music sx"></i> Browse');
	}
}

// update countdown
function refreshTimer(startFrom, stopTo, state){
    if (state == 'play') {
        $('#countdown-display').countdown('destroy');
        $('#countdown-display').countdown({since: -(startFrom), compact: true, format: 'MS'});
    } else if (state == 'pause') {
        $('#countdown-display').countdown('destroy');
        $('#countdown-display').countdown({since: -(startFrom), compact: true, format: 'MS'});
        $('#countdown-display').countdown('pause');
    } else if (state == 'stop') {
        $('#countdown-display').countdown('destroy');
        $('#countdown-display').countdown({since: 0, compact: true, format: 'MS'});
        $('#countdown-display').countdown('pause');
    }
}

// update right knob
function refreshKnob(json){
    window.clearInterval(GUI.currentKnob)
    var initTime = json['song_percent'];
    var delta = json['time'] / 1000;
    $('#time').val(initTime*10).trigger('change');
    if (GUI.state == 'play') {
        GUI.currentKnob = setInterval(function() {
            if (GUI.visibility == 'visible') {
                initTime = initTime + 0.1;
            } else {
                initTime = initTime + 100/json['time'];
            }
            $('#time').val(initTime*10).trigger('change');
            //document.title = Math.round(initTime*10) + ' - ' + GUI.visibility;
        }, delta * 1000);
    }
}

// time conversion
function timeConvert(seconds) {
    if(isNaN(seconds)) {
    	display = '';
    } else {
    	minutes = Math.floor(seconds / 60);
    	seconds -= minutes * 60;
    	mm = (minutes < 10) ? ('0' + minutes) : minutes;
    	ss = (seconds < 10) ? ('0' + seconds) : seconds;
    	display = mm + ':' + ss;
    }
    return display;
}

// reset countdown
function countdownRestart(startFrom) {
    $('#countdown-display').countdown('destroy');
    $('#countdown-display').countdown({since: -(startFrom), compact: true, format: 'MS'});
}

// set volume with knob
function setVolume(val) {
    GUI.volume = val;
    GUI.halt = 1;
    $('#volumemute').removeClass('btn-primary');
    sendCmd('setvol ' + val);
}

// adjust knob with volume
function adjustKnobVolume(val) {
    $('#volume').val(val);
}

// scrolling
function customScroll(list, destination, speed) {
    if (typeof(speed) === 'undefined') speed = 500;
    var entryheight = parseInt(1 + $('#' + list + '-1').height());
    var centerheight = parseInt($(window).height()/2);
    var scrolltop = $(window).scrollTop();
    if (list == 'db') {
        var scrollcalc = parseInt((destination)*entryheight - centerheight);
        var scrolloffset = scrollcalc;
    } else if (list == 'pl') {
        //var scrolloffset = parseInt((destination + 2)*entryheight - centerheight);
        var scrollcalc = parseInt((destination + 2)*entryheight - centerheight);
        if (scrollcalc > scrolltop) {
            var scrolloffset = '+=' + Math.abs(scrollcalc - scrolltop) + 'px';
        } else {
            var scrolloffset = '-=' + Math.abs(scrollcalc - scrolltop) + 'px';
        }
    }
    if (scrollcalc > 0) {
        $.scrollTo( scrolloffset , speed );
    } else {
        $.scrollTo( 0 , speed );
    }
    //$('#' + list + '-' + (destination + 1)).addClass('active');
}

function randomScrollPL() {
    var n = $(".playlist li").size();
    var random = 1 + Math.floor(Math.random() * n);
    customScroll('pl', random);
}
function randomScrollDB() {
    var n = $(".database li").size();
    var random = 1 + Math.floor(Math.random() * n);
    customScroll('db', random);
}

//Social Sharing
$('a.tweet').click(function(e){
    var urlTwitter = 'https://twitter.com/home?status=%E2%99%AB%20%23NowPlaying+' + GUI.currentartist.replace(/\s+/g, '+') + '+-+' + GUI.currentsong.replace(/\s+/g, '+') + '+with+%40Volumio+http%3A%2F%2Fvolumio.org%2F+';
    $('a.tweet').attr('href', urlTwitter);
});
$('a.facebook').click(function(e){
    var urlFacebook = 'https://www.facebook.com/sharer.php?u=http%3A%2F%2Fvolumio.org%2F&display=popup';
    $('a.facebook').attr('href', urlFacebook);
});
$('a.googleplus').click(function(e){
    var urlGooglePlus = 'https://plus.google.com/share?url=http%3A%2F%2Fvolumio.org%2F';;
    $('a.googleplus').attr('href', urlGooglePlus);
});