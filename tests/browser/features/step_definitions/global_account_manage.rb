Given(/^I am at Special:CentralAuth$/) do
  visit(SpecialCentralAuthPage)
end

When(/^I lookup an invalid user$/) do
  on(SpecialCentralAuthPage).lookup_user("invalid centralauth user name")
end

When(/^I lookup a valid user$/) do
  on(SpecialCentralAuthPage).lookup_user(user)
end

Then(/^target element should be there$/) do
  expect(on(SpecialCentralAuthPage).target_field_element).to exist
end

Then(/^error box should be visible$/) do
  expect(on(SpecialCentralAuthPage).error_box_element).to be_visible
end

Then(/^global account information should be visible$/) do
  expect(on(SpecialCentralAuthPage).centralauth_info_element).to be_visible
end
