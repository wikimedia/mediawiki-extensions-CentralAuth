class SpecialCentralAuthPage
  include PageObject

  page_url 'Special:CentralAuth'

  text_field(:target_field, id: 'target')
  div(:error_box, class: 'error')
  button(:submit, id: 'centralauth-submit-find')
  fieldset(:centralauth_info, id: 'mw-centralauth-info')

  def lookup_user(username)
    target_field_element.when_present.send_keys(username, :enter)
    submit_element.when_present.click
  end
end
