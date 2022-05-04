(function ($) {
  $(".msg").hide();
  $(".loader").hide();
  function test_email() {
    var email = document.getElementById("email").value;
    if (email) {
      $(".email_btn").css({ "background-color": "#cccccc", color: "#808080" });
      $(".emailForm input[type='email']").css("border-color", "#000");
      $(".loader").show();
      $.ajax({
        url: myVar.ajax_url,
        type: "POST",
        data: {
          action: "my_email_hook",
          email: email,
        },
        success: function (response) {
          $(".email_btn").css({ "background-color": "#2271b1", color: "#fff" });
          $(".loader").hide();
          $(".msg").html("Mail sent successfully..").show();
        },
      });
    } else {
      $("#email").attr("placeholder", "Please insert email id..");
      $(".emailForm input[type='email']").css("border-color", "#ff0000");
    }
  }
  $(".email_btn").on("click", function (event) {
    event.preventDefault();
    test_email();
  });
})(jQuery);
