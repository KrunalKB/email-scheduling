// FOR SENDING EMAIL
(function ($) {
  $(".resp").hide();
  function registerData() {
    $.ajax({
      url: myVar.ajax_url,
      type: "POST",
      data: {
        action: "my_ajax_hook",
      },
      success: function (response) {
        $(".resp").show();
        $(".resp").html("Email sent successfully");
      },
    });
  }
  $("#sendEmail").on("click", function (event) {
    event.preventDefault();
    registerData();
  });
})(jQuery);

// FOR SAVE EMAIL TEMPLATE
(function ($) {
  $(".response").hide();
  function saveEmail() {
    var content = $("#mail_template_id").val();
    $.ajax({
      url: myVar.ajax_url,
      type: "POST",
      data: {
        action: "email_template_hook",
        nonce: myVar.nonce,
        content: content,
      },
      success: function (response) {
        $(".response").show();
        $(".response").html("Data Saved!");
      },
    });
  }
  $("#saveEmail").on("click", function (event) {
    event.preventDefault();
    saveEmail();
  });
})(jQuery);
