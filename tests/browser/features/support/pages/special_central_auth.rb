class SpecialCentralAuthPage
  include PageObject

  include URL
  page_url URL.url("Special:CentralAuth")

  text_field(:target_field, id: "target")
  div(:error_box, class: "error")
  # Submit button only has a value until Iab8cb5ad1757ed8eaa7873d54204d6d420da5f82
  button(:submit, value: "View user info")
  fieldset(:centralauth_info, id:"mw-centralauth-info")

  def lookup_user( username )
    self.target_field_element.when_present.send_keys(username)
    submit_element.when_present.click
  end
end
