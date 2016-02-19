class LoginPage
  include PageObject

  page_url 'Special:UserLogin'

  a(:username_displayed, title: /Your user page/)
end
