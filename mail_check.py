import mysql.connector
import time
import requests
import json
import smtplib
from email.mime.text import MIMEText
import datetime
import logging



# mail alert function
def mail_alert():
    print("mail function")
    # print(set_arr)
    # print(final_res)
    # to_mail_arr = set_arr[9].split(',')
    # cc_mail_arr = set_arr[10].split(',')
    print("array mail")
    # print(to_mail_arr)
    # for i in range(len(to_mail_arr)):

    subject = "SmartMach Alert Testing"
    body = "SmartMach Testing"
    sender = "support@smartories.com"
    to = 'naveenkumarnk18420@gmail.com,kpashokkumar08@gmail.com'
    cc = 'deerajalagarsmayol7xl9@gmail.com'
    # bcc = ['qramkumar@gmail.com','kpashokkumar08@gmail.com']
    # cc_recipents = cc_mail_arr[i]
    # print(type(to))
    to_mail_arr = to.split(',')
    cc_mail_arr = cc.split(',')
    print(type(to_mail_arr))
    if len(to_mail_arr)>1 and len(cc_mail_arr)>1:
        recipients = to_mail_arr + cc_mail_arr

    elif len(to_mail_arr)>1 and len(cc_mail_arr)<=1:
        recipients = to_mail_arr + [cc]

    elif len(to_mail_arr)<=1 and len(cc_mail_arr)>1:
        recipients = [to]+cc_mail_arr
    
    elif len(to_mail_arr)<=1 and len(cc_mail_arr)<=1:
        recipients = [to] + [cc]
        
    password = "05dqXkeU8gHm"
    msg = MIMEText(body)
    msg['Subject'] = subject
    msg['From'] = sender
    msg['To'] = to
    # msg['Cc'] = cc_recipents
     
    smtp_server = smtplib.SMTP_SSL('smtppro.zoho.in',465)
    smtp_server.login(sender, password)
    smtp_server.sendmail(sender, recipients, msg.as_string())
    smtp_server.quit()
    print("mail function")
# get work order id 

mail_alert()