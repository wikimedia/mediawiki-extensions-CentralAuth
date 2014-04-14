class SpecialCentralAuthPage
  include PageObject

  include URL
  page_url URL.url("Special:CentralAuth")

  text_field(:target_field, id: "target")
  div(:error_box, class: "error")
  button(:submit, id: "centralauth-submit-find")
  fieldset(:centralauth_info, id:"mw-centralauth-info")

  def lookup_user( username )
    self.target_field_element.when_present.send_keys(username)
    submit_element.when_present.click
  end
end
