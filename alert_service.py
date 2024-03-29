import mysql.connector
import time
import requests
import json
import smtplib
from email.mime.text import MIMEText
import datetime
import logging

# logger file creation
def alert_service_log(msg,val):
    logging.basicConfig(filename='alert_service_log.log', format='%(asctime)s %(message)s', level=logging.DEBUG)
    single_msg = str(msg)+str(val)
    logging.debug(single_msg)

# logger header
def logger_header_title(hmsg,count):
    file1 = open("alert_service_log.log", "a")  # append mode
    file1.write("Alert service starting =========="+str(hmsg)+"==========count no : "+str(count)+"=====================\n")
    file1.close()


# logger space
def logger_space():
    file2 = open("alert_service_log.log", "a")
    file2.write(" ")
    file2.write(" ")
    file2.close()

# mysql alert settings matched timstamp updated
def update_matched_time(aid,future_time):
    db_connect = mysql.connector.connect(
        host="localhost",
        user="root",
        password="quantanics123",
        database="S1002"
    )

    mycursor = db_connect.cursor()
    sql = "UPDATE alert_settings SET matched_time_stamp=%s WHERE alert_id=%s"
    val = (future_time,aid)
    mycursor.execute(sql,val)
    db_connect.commit()
    return True




# mail alert function
def mail_alert(set_arr,final_res):
    print("mail function")
    print(set_arr)
    print(final_res)
   
    to_mail_arr = set_arr[9].split(',')
    cc_mail_arr = set_arr[10].split(',')
   
    if len(to_mail_arr)>1 and len(cc_mail_arr)>1:
        recipients = to_mail_arr + cc_mail_arr

    elif len(to_mail_arr)>1 and len(cc_mail_arr)<=1:
        recipients = to_mail_arr + [set_arr[10]]

    elif len(to_mail_arr)<=1 and len(cc_mail_arr)>1:
        recipients = [set_arr[9]]+cc_mail_arr
    
    elif len(to_mail_arr)<=1 and len(cc_mail_arr)<=1:
        recipients = [set_arr[9]] + [set_arr[10]]
        
    # for i in range(len(to_mail_arr)):
    print("recipients")
    print(recipients)
    subject = "SmartMach Alert"+' '+set_arr[15]
    body = set_arr[2]+' '+str(final_res)+' '+set_arr[3]+' '+set_arr[4]
    sender = "support@smartories.com"

    to_arr_email = set_arr[9].split(',')
    cc_arr_email = set_arr[10].split(',')
    password = "05dqXkeU8gHm"
    msg = MIMEText(body)
    msg['Subject'] = subject
    msg['From'] = sender
    msg['To'] = ', '.join(to_arr_email)
    msg['Cc'] = ', '.join(cc_arr_email)
      
    smtp_server = smtplib.SMTP_SSL('smtppro.zoho.in',465)
    smtp_server.login(sender, password)
    smtp_server.sendmail(sender, recipients, msg.as_string())
    smtp_server.quit()
    print("mail function end")
# get work order id 
def get_work_order_id(tmp_site_id):
    tmp_site_id = tmp_site_id.upper()
    tmp_split = tmp_site_id.split('S')
    site_number = int(tmp_split[1])-1000

    mydb_con = mysql.connector.connect(
        host="localhost",
        user="root",
        password="quantanics123",
        database=tmp_site_id
    )
    mycursor_obj= mydb_con.cursor()
    mycursor_obj.execute("SELECT * FROM work_order_management")
    myresult = mycursor_obj.fetchall()
    if len(myresult)>0:
        work_order_id = 'S'+str(site_number)+'-W'+str(len(myresult))

    else:
        work_order_id = 'S'+str(site_number)+'-W1'

    return work_order_id



# work order table insertion 
def work_alert(set_arr,final_res):
    print("work function")
    mydb = mysql.connector.connect(
        host="localhost",
        user="root",
        password="quantanics123",
        database="S1002"
    )
    mycon = mydb.cursor()
   
    if set_arr[21]!="email":
        
        # sql_work = "INSERT INTO `work_order_management`(`r_no`, `work_order_id`, `type`, `title`, `description`, `priority_id`, `assignee`, `due_date`, `status_id`, `cause_id`, `action_id`, `lable_id`, `comment_id`, `attachment_id`, `status`, `last_updated_by`, `last_updated_on`) VALUES ('[value-1]','[value-2]','[value-3]','[value-4]','[value-5]','[value-6]','[value-7]','[value-8]','[value-9]','[value-10]','[value-11]','[value-12]','[value-13]','[value-14]','[value-15]','[value-16]','[value-17]')"
        tmp_work_order_id = get_work_order_id("demo")
        
        due_date = datetime.datetime.today()+datetime.timedelta(days = int(set_arr[14]))
        due_date = due_date.strftime("%Y-%m-%d")
        
        sql_work = "INSERT INTO `work_order_management`( `work_order_id`,`type`, `title`,`priority_id`, `assignee`,`due_date`,`status_id`,`lable_id`,`status`, `last_updated_by`) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)"
        val = (tmp_work_order_id,set_arr[11],set_arr[12],str(set_arr[17]),set_arr[13],due_date,'1',set_arr[8],'1',set_arr[18])
        mycon.execute(sql_work, val)
        mydb.commit()
        print("insertion Successfully Work order Management")



# condition checking function 
def check_metrics(res,li):
    result = ""
    set_val = float(li[4])
    
   
    if res is not None:
        get_val = float(res)
    elif res is None:
        get_val = 0

    alert_msg = "alert metrics aid"+str(li[0])+" the metrics is"+str(get_val)+" "+str(li[3])+" "+str(set_val)
    
    print("api result data:\t"+str(get_val))
    print("existing data:\t"+str(set_val))
    if(li[3]=='<'):
        if(get_val < set_val):
            result="success"
        else:
            result="fail"
    elif(li[3]=='>'):
        if(get_val > set_val):
            result="success"
        else:
            result="fail"
    elif(li[4]=='=='):
        if(set_val==get_val):
            result="success"
        else:
            result="fail"
    elif(li[4]==">="):
        if(get_val >= set_val):
            result="success"
        else:
            result="fail"
    elif(li[4]=="<="):
        if(get_val <= set_val):
            result="success"
        else:
            result="fail"
        
    
    alert_service_log(alert_msg,result)
  
    one_hour_Time = datetime.datetime.now()
    one_hour_extend = one_hour_Time + datetime.timedelta(hours = int(1))
    one_hour_extend = one_hour_extend.strftime("%Y-%m-%dT%H:00:00")
    
    current_Time_compare = datetime.datetime.now()
    future_date_Time = current_Time_compare + datetime.timedelta(hours = int(li[5]))
    future_date_Time = future_date_Time.strftime("%Y-%m-%dT%H:00:00")
    current_Time_compare = current_Time_compare.strftime("%Y-%m-%dT%H:00:00")
    if(result == "success"):
       
        print("check metrics function")
        print(li[22])
        if(li[22]==None):
            
            update_matched_time(li[0],future_date_Time)
            if li[20]=="all":
                work_alert(li,get_val)
                mail_alert(li,get_val)

            elif li[20]=="work":
                work_alert(li,get_val)
            elif li[20]=="email":
                mail_alert(li,get_val)
               
        elif(li[22]==current_Time_compare):
           
            update_matched_time(li[0],future_date_Time)
            if li[20]=="all":
                work_alert(li,get_val)
                mail_alert(li,get_val)
            elif li[20]=="work":
                work_alert(li,get_val)
            elif li[20]=="email":
                mail_alert(li,get_val)
                
        else:
            if current_Time_compare > li[22]:
                update_matched_time(li[0],one_hour_extend)
            
    else:
        if(li[22]==None):
            update_matched_time(li[0],one_hour_extend)
        elif(li[22]==current_Time_compare):
            update_matched_time(li[0],one_hour_extend)
        elif(current_Time_compare>li[22]):
            update_matched_time(li[0],one_hour_extend)
        


# metrics checking function
def analysis_metrics(i):
    current_datetime = datetime.datetime.now()
    #current_datetime = unicode(now.replace(microsecond=0))
    current_date_time = current_datetime.strftime("%Y-%m-%dT%H:00:00")
    previous_date_Time = datetime.datetime.now() - datetime.timedelta(hours = int(i[5]))
    previous_date_Time = previous_date_Time.strftime("%Y-%m-%dT%H:00:00")
    
    print("fromt time:\t"+previous_date_Time)
    print("to time:\t"+current_date_time)
    par = {
        "from_time": previous_date_Time,
        "to_time": current_date_time,
        "machine_arr":i[6],
        "part_arr":i[7],
        "site_id":"S1002",
        "res":i[2],
    }
    # api_array = requests.post("http://165.22.208.52/alert_service/index.php",params=par)
    api_array = requests.post("http://localhost/alert_service_change/corrected_index.php",params=par)

    #final_api_record = json.dumps(api_array)
    alert_service_log("Alert service api status",api_array.status_code)
    if api_array.status_code == 200:
        rp = api_array.json()
    
        alert_service_log("Alert service api data",rp)
        
        check_metrics(rp,i)          
        

   
def mysql_res():
    mydb = mysql.connector.connect(
        host="localhost",
        user="root",
        password="quantanics123",
        database="S1002"
    )

    mycursor = mydb.cursor()
    mycursor.execute("SELECT * FROM alert_settings WHERE alert_status!=1")
    myres = mycursor.fetchall()

    for i in myres:
        alert_service_log("Alert service id",i)
        analysis_metrics(i)

k = 0
while True:
    
    now = datetime.datetime.now()
    if(int(now.strftime("%M"))>=0):
        print("=======================Alert service started============================")
        k = k+1
        current_time_insert_log = datetime.datetime.now()
        current_time_insert_log = current_time_insert_log.strftime("%Y-%m-%dT%H:00:00")
        logger_header_title(current_time_insert_log,k)
        logger_space()
        mysql_res()
        print("end count of ",k)
        logger_space()
        time.sleep(60)
