/**
 * Low Events JS file
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */

// Make sure LOW namespace is valid
if (typeof LOW == 'undefined') var LOW = new Object;

(function($){

// --------------------------------------
// Create Low Date object
// --------------------------------------

LOW.Date = function(date, time) {

	// Private var of JS date object
	var _date = new Date();

	// Add padding to number
	var _pad = function(i) {
		return ('0' + i).slice(-2);
	};

	// Make sure date given is valid and JS understands it
	var _getDate = function(str) {
		var m;

		if ( ! str || ! (m = str.match(/^(19\d\d|20\d\d)-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])$/))) {
			return null;
		}

		return [parseInt(m[1]), m[2] - 1, m[3] * 1];
	};

	// Make sure time is valid and in 24h format
	var _getTime = function(str) {
		var m;

		if ( ! str || ! (m = str.toLowerCase().match(/^(\d{1,2}):(\d{1,2})\s?([ap]m)?$/i))) {
			return null;
		}

		if ( ! m[3]) return [m[1], m[2]];

		var hour = parseInt(m[1] * 1);

		if (hour == 12) {
			var hours = (m[3] == 'pm') ? 12 : 0;
		} else {
			var hours = (hour + (m[3] == 'pm' ? 12 : 0));
		}

		return [hours, parseInt(m[2])];
	};

	var obj = {
		// Set given date and time to this object
		set: function(d, t) {
			if (d = _getDate(d)) {
				_date.setFullYear(d[0]);
				_date.setMonth(d[1]);
				_date.setDate(d[2]);
			}

			if (t = _getTime(t)) {
				_date.setHours(t[0]);
				_date.setMinutes(t[1]);
			}
		},

		// Add amount of milliseconds to this object's date
		add: function(ms) {
			_date.setTime(_date.getTime() + ms);
		},

		// Return timestamp in milliseconds
		stamp: function() {
			return _date.getTime();
		},

		// Return the date in YYYY-MM-DD format
		getDate: function() {
			return [
				_date.getFullYear(),
				_pad(_date.getMonth() + 1),
				_pad(_date.getDate())
			].join('-');
		},

		// Return the time in HH:II format
		getTime: function() {
			return [
				_pad(_date.getHours()),
				_pad(_date.getMinutes())
			].join(':');
		},

		// Check if given date is valid
		isValidDate: function(d) {
			return _getDate(d);
		},

		// Check if given time is valid
		isValidTime: function(t) {
			return _getTime(t);
		}
	};

	obj.set(date, time);

	return obj;
};

// --------------------------------------
// Create Low Events object
// --------------------------------------

LOW.Events = function(el) {

	// jQuery objects
	var $el        = $(el),
		$startDate = $el.find('.start.date'),
		$startTime = $el.find('.start.time'),
		$endDate   = $el.find('.end.date'),
		$endTime   = $el.find('.end.time'),
		$allDay    = $el.find(':checkbox');

	// Translated data-attributes to settings
	var Settings = {
		firstDay : $el.data('first-day'),
		timeFormat : ($el.data('time-format') || '24'),
		timeInterval : ($el.data('time-interval') || '30'),
		lang : {}
	};

	// Change timeFormat from 'us' or 'eu' to correct format
	Settings.timeFormat = (Settings.timeFormat == '12') ? 'g:ia' : 'H:i';

	// Check language attributes
	var langKeys = ['decimal', 'mins', 'hr', 'hrs'];

	for (var i = 0, l = langKeys.length; i < l; i++) {
		var val = $el.data('lang-'+langKeys[i]);
		if (val) {
			Settings.lang[langKeys[i]] = val;
		}
	}

	// Local Start date, End date and time difference
	var start = new LOW.Date($startDate.val(), $startTime.val()),
		end   = new LOW.Date($endDate.val(), $endTime.val()),
		diff  = end.stamp() - start.stamp(),
		day   = 60 * 60 * 24 * 1000,
		last  = {};

	// Keep track of last valid start/end times
	last.startTime = $startTime.val();
	last.endTime = $endTime.val();

	// --------------------------------------

	// Is the start and end date the same?
	function _isSameDay() {
		return start.getDate() == end.getDate();
	};


	function _isWithin24Hours() {
		//return start.getDate() == end.getDate();
		return (end.stamp() - start.stamp()) < day;
	};

	function toggleAllDay() {
		$el.toggleClass('low-all-day');
	};

	// --------------------------------------

	// Add datepicker to date fields
	$startDate.datepicker({
		showAnim: false,
		dateFormat: $.datepicker.W3C,
		defaultDate: start.getDate(),
		firstDay: Settings.firstDay,
		showOtherMonths: true,
		selectOtherMonths: true,
		prevText: '&larr;',
		nextText: '&rarr;',
	});

	$endDate.datepicker({
		showAnim: false,
		dateFormat: $.datepicker.W3C,
		defaultDate: end.getDate(),
		firstDay: Settings.firstDay,
		showOtherMonths: true,
		selectOtherMonths: true,
		prevText: '&larr;',
		nextText: '&rarr;',
	});

	// Add timepicker to time fields
	$startTime.timepicker({
		timeFormat: Settings.timeFormat,
		step: Settings.timeInterval,
		show2400: false
	});

	$endTime.timepicker({
		timeFormat: Settings.timeFormat,
		step: Settings.timeInterval,
		show2400: false,
		minTime: start.getTime(),
		showDuration: _isWithin24Hours(),
		lang: Settings.lang
	});

	// All Day toggle Show/hide times based on checkbox
	$allDay.change(toggleAllDay);

	// Make it a Datepair, combined with the jQuery UI Datepicker
	$el.datepair({
	    parseDate: function (el) {
	        var utc = new Date($(el).datepicker('getDate'));
	        return utc && new Date(utc.getTime() + (utc.getTimezoneOffset() * 60000));
	    },
	    updateDate: function (el, v) {
	        var utc = new Date();
	        $(el).datepicker('setDate', new Date(v.getTime() - (utc.getTimezoneOffset() * 60000)));
	    }
	});

};

// Execute onload
$(function(){
	$('.low-events').each(function(){
		new LOW.Events(this);
	});
});

})(jQuery);