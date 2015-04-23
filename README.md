# ingress-portal-gdocs-spreadsheet
A tool to create a Google Docs spreadsheet of your local portals

Currently wrangling code... stand by

## Usage

### Google Docs

Here is a sample Google Doc:

...

### Client

First install TamperMonkey, with one or both of the following scripts:

This script will add every portal visible in the intel map to your spreadsheet

```
// ==UserScript==
// @name         Basic portal import
// @namespace    https://ingress.com/
// @version      0.1
// @description  Sends data about every portal in view to the Ingress Portal Spreadsheet Learn more: https://github.com/bigtbigtbigt/ingress-portal-gdocs-spreadsheet
// @author       Tony Landa
// @match        https://wwws.ingress.com/intel/
// @grant        none
// ==/UserScript==

nemesis.dashboard.data.Portal.createSimple = function(a, b, c) {
a = new nemesis.dashboard.data.Portal(a, b.team, c);
a.lat = b.latE6 / 1E6;
a.lng = b.lngE6 / 1E6;
a.level = b.level;
a.health = b.health;
a.resonatorCount = b.resCount;
a.isCaptured = a.resonatorCount > 0;
a.title = b.title;
a.image = nemesis.dashboard.data.ImageUrlUtils.getPortalImageUrl(b.image);
// Injected code
console.log ("createSimple: " + a.title);
console.log ("http://www.landaenterprises.com/ingress/spreadsheet/test/?debug=true&json=" + encodeURIComponent(JSON.stringify(a)));
url = "http://www.landaenterprises.com/ingress/spreadsheet/test/?json=" + encodeURIComponent(JSON.stringify(a));
var img=new Image();
img.src=url;
// End injected code
return a
};
```

This script will only add portals when you click them, but you get more data this way

```
// ==UserScript==
// @name         Controlled portal import
// @namespace    https://ingress.com/
// @version      0.1
// @description  Sends data about every portal UPON CLICK to the Ingress Portal Spreadsheet, which includes more data than the basic portal import Learn more: https://github.com/bigtbigtbigt/ingress-portal-gdocs-spreadsheet
// @author       Tony Landa
// @match        https://wwws.ingress.com/intel/
// @grant        none
// ==/UserScript==

nemesis.dashboard.data.Portal.prototype.updateDetails = function(a) {
this.hasDetails_ = true;
this.lat = a.locationE6.latE6 / 1E6;
this.lng = a.locationE6.lngE6 / 1E6;
this.linkedResonators = nemesis.dashboard.data.Resonator.create(a.resonatorArray.resonators);
this.resonatorCount = this.linkedResonators.length;
this.level = nemesis.dashboard.data.Portal.computePortalLevel_(this.linkedResonators);
this.health = nemesis.dashboard.data.Portal.getPortalHealthPercent_(this.linkedResonators);
if("map" in a.descriptiveText) {
if("TITLE" in a.descriptiveText.map) {
this.title = a.descriptiveText.map.TITLE
}
}else {
this.title = ""
}
this.image = nemesis.dashboard.data.ImageUrlUtils.getPortalImageUrl(a.imageByUrl ? a.imageByUrl.imageUrl : "");
var b = goog.array.filter(a.portalV2.linkedModArray, goog.isDefAndNotNull);
this.linkedMods = goog.array.map(b, function(a) {
return{rarity:nemesis.dashboard.data.Portal.RARITY_STRINGS_[a.rarity], name:a.displayName, stats:a.stats, installer:a.installingUser}
});
if(a.captured) {
this.isCaptured = true, this.capturedTime = (new goog.i18n.DateTimeFormat("MM/dd/yyyy")).format(new Date(parseInt(a.captured.capturedTime, 10))), this.capturingPlayer = a.captured.capturingPlayerId
}
// Injected code
if(a.captured) this.capturedTimeExact = a.captured.capturedTime;
console.log ("updateDetails: " + this.title);
console.log ("http://www.landaenterprises.com/ingress/spreadsheet/test/?debug=true&json=" + encodeURIComponent(JSON.stringify(this)));
url = "http://www.landaenterprises.com/ingress/spreadsheet/test/?json=" + encodeURIComponent(JSON.stringify(this));
var img=new Image();
img.src=url;
// End injected code
};
```