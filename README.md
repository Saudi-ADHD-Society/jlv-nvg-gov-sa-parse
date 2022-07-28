# NVG volunteer opportunity parser
This Wordpress plugin creates a shortcode `[nvg_fetch]` that fetches the Saudi ADHD Society's latest volunteer opportunities posted on the [Saudi National Volunteering portal](https://nvg.gov.sa/) and displays them in a table. This contributes towards our goal of automation and reduces duplication of effort, as they can be displayed on our website without having to manually enter them in two places.

## How it works
In brief, it uses CURL to fetch the contents of a filtered page of search results with just the Saudi ADHD Society's volunteer opportunities, and parses them using DOMDocument and DomXPath. This is described in more detail below.

## Search Results Page
Although the site doesn't offer a profile page for each organization with just that organization's volunteer opportunities on it, there is a search form wich allows you to search by organization name. The search form uses AJAX and doesn't update the URL, so we cannot just copy the URL from the address bar to link directly to the search results page. But by examining the code on the NVG site, it is possible to identify the necessary variables to construct our own query.

This is the form element: 
```
<form id="filter_form" data-ajax="true" data-ajax-method="GET" data-ajax-mode="replace" data-ajax-update="#result" data-ajax-begin="formSubmit" data-ajax-complete="ajaxDone" action="/Opportunities/GetOpportunities" method="post">
// ...
</form>
```
Here is some javascript:
```
function getOpportunities(t, e) { $.ajax({ url: "/Opportunities/GetOpportunities", data: { page: t, isDone: e }, success: function (t) { $("#loader").hide(), $("#result").hide().fadeIn("fast").html(t) } }) } 

function getOpportunity(t) { $.ajax({ url: "/Opportunities/GetDetails/" + t, success: function (t) { $("#result").hide().fadeIn("fast").html(t) } }) } 
```

This is the form input for the organization name:
```
<input class="form update_input" type="text" name="organizationName" id="organizationName" placeholder="Search by organization name" style="padding-top: 0; flex: 1; padding-left: 45px;">
```

Now we know that:
1. The form uses POST.
2. The action URL for the opportunities list is `/Opportunities/GetOpportunities`.
3. The action URL for the opportunity details is `/Opportunities/GetDetails/`.
4. The organization name is called `organizationName`.

Putting this together we can construct a link for the list like so:
`https://nvg.gov.sa/Opportunities/GetOpportunities/?organizationName=الجمعية%20السعودية%20لاضطراب%20فرط%20الحركة%20وتشتت%20الانتباه%20(إشراق)`
and for the details like so:
`https://nvg.gov.sa/Opportunities/GetDetails/12345678-1234-1234-1234-1234567890ab`

### Parsing
The opportunity list HTML content returned by CURL needs a bit of tidying up to be useful, so the first thing to do is choose the elements we wish to keep. The following classes were identified: `card_title`, `card_location`, `card_text`, `days_number`, `dates`, `seats_number`, `join_btn`. These are self-explanatory, except the final one, which is the hyperlink class.

Filtering out each of these in turn, and then assembling them all into a table allows us to display the latest volunteer opportunities on our website without having to manually update them.

The opportunity detail page doesn't have a unique class or ID for each element, so we will have to target them using (div>div>div>div>div>p).

### How to use
1. Make two pages: Opportunities and Details, making Opportunities the parent of Details.
2. On the Opportunities page, insert the shortcode `[nvg_fetch detail_page="/Opportunity/Details/"]`.
3. On the Details page, insert the shortcode `[nvg_fetch_details]`.
