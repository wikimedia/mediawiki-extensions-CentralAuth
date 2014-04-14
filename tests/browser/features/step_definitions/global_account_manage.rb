Given(/^I am at Special:CentralAuth$/) do
  visit(SpecialCentralAuthPage)
end

When(/^I lookup an invalid user$/) do
  on(SpecialCentralAuthPage).lookup_user("invalid centralauth user name")
end

When(/^I lookup a valid user$/) do
  on(SpecialCentralAuthPage).lookup_user(ENV["MEDIAWIKI_USER"])
end

Then(/^target element should be there$/) do
  on(SpecialCentralAuthPage).target_field_element.should exist
end

Then(/^error box should be visible$/) do
  on(SpecialCentralAuthPage).error_box_element.should be_visible
end

Then(/^global account information should be visible$/) do
  on(SpecialCentralAuthPage).centralauth_info_element.should be_visible
end
