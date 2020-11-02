$(document).ready(function() {
  $('#role').change(function() {
    const tr = $(this).closest('tr');
    const selectedRole = $(this).val();
    const action = `${DIRPAGE}userspanel/assignRole/${$(tr).attr('data-item')}/${selectedRole}`;

    const error = $('[data-error="roles"]');
    const errorMsg = $(error).find('.error-msg');

    $.ajax({
      url: action,
      type: 'POST',
    }).done(function(response) {
      if (response.length !== 0) {
        $(error).removeClass('d-none');
        $(error).addClass('d-block');
        $(errorMsg).html(response).fadeIn();
      } else {
        window.location.href = `${DIRPAGE}userspanel`;
      }
    }).fail(function() {
      $(error).removeClass('d-none');
      $(error).addClass('d-block');
      $(errorMsg).html('Ops! Algo de errado aconteceu!').fadeIn();
    });
  });
});