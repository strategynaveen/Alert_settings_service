import mysql.connector
import time
import requests
import json
import smtplib
from email.mime.text import MIMEText
import datetime


# mysql alert settings matched timstamp updated
def update_matched_time(aid,future_time):
    db_connect = mysql.connector.connect(
        host="localhost",
        username="root",
        password="",
        database="s1001"
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
    # cc_mail_arr = set_arr[10].split(',')
    for i in range(len(to_mail_arr)):

        subject = "SmartMach Alert"+' '+set_arr[16]
        body = set_arr[2]+' '+str(final_res)+' '+set_arr[3]+' '+set_arr[4]
        sender = "support@smartories.com"
        recipients = to_mail_arr[i]
        # cc_recipents = cc_mail_arr[i]
        password = "05dqXkeU8gHm"
        msg = MIMEText(body)
        msg['Subject'] = subject
        msg['From'] = sender
        msg['To'] = recipients
        # msg['Cc'] = cc_recipents
        
        smtp_server = smtplib.SMTP_SSL('smtppro.zoho.in', 465)
        smtp_server.login(sender, password)
        smtp_server.sendmail(sender, recipients, msg.as_string())
        smtp_server.quit()
        print("mail function")
# get work order id 
def get_work_order_id(tmp_site_id):
    tmp_site_id = tmp_site_id.upper()
    tmp_split = tmp_site_id.split('S')
    site_number = int(tmp_split[1])-1000

    mydb_con = mysql.connector.connect(
        host="localhost",
        username="root",
        password="",
        database=tmp_site_id
    )
    mycursor_obj= mydb_con.cursor()
    mycursor_obj.execute("SELECT * FROM work_order_management")
    myresult = mycursor_obj.fetchall()
    if len(myresult)>0:
        work_order_id = 'S'+str(site_number)+'-W'+str(len(myresult))
        #return work_order_id
    else:
        work_order_id = 'S'+str(site_number)+'-W1'

    return work_order_id



# work order table insertion 
def work_alert(set_arr,final_res):
    print("work function")
    mydb = mysql.connector.connect(
        host="localhost",
        username="root",
        password="",
        database="s1001"
    )
    mycon = mydb.cursor()
    print("work array")
    # print(set_arr)
    # print(final_res)
    if set_arr[21]!="email":
        # sql_work = "INSERT INTO `work_order_management`(`r_no`, `work_order_id`, `type`, `title`, `description`, `priority_id`, `assignee`, `due_date`, `status_id`, `cause_id`, `action_id`, `lable_id`, `comment_id`, `attachment_id`, `status`, `last_updated_by`, `last_updated_on`) VALUES ('[value-1]','[value-2]','[value-3]','[value-4]','[value-5]','[value-6]','[value-7]','[value-8]','[value-9]','[value-10]','[value-11]','[value-12]','[value-13]','[value-14]','[value-15]','[value-16]','[value-17]')"
        tmp_work_order_id = get_work_order_id("s1001")
        # print(tmp_work_order_id)
        print(set_arr[14])
        due_date = datetime.datetime.today()+datetime.timedelta(days = int(set_arr[14]))
        due_date = due_date.strftime("%Y-%m-%d")
        print(due_date)
        sql_work = "INSERT INTO `work_order_management`( `work_order_id`,`type`, `title`,`priority_id`, `assignee`,`due_date`,`status_id`,`lable_id`,`status`, `last_updated_by`) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)"
        val = (tmp_work_order_id,set_arr[11],set_arr[12],set_arr[17],set_arr[13],due_date,1,set_arr[8],1,set_arr[18])
        mycon.execute(sql_work, val)
        mydb.commit()
        print("insertion Successfully Work order Management")



# condition checking function 
def check_metrics(res,li):
    result = ""
    set_val = float(li[4])
    if li[2]=="planned_downtime":
        get_val = float(res)
    elif li[2]=="unplanned_downtime":
        get_val = float(res)
    elif li[2]=="planned_machine_off":
        get_val = float(res)
    elif li[2]=="unplanned_machine_off":
        get_val = float(res)
    else:
        get_val = float(res)

    #print(get_val)
    #print(set_val)
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
        
    print(result)
    print(get_val)
    print(set_val)
    print(li[4])
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
            print("metrics empty string")
            print(future_date_Time)
            print(li[22])
            update_matched_time(li[0],future_date_Time)
            if li[20]=="all":
                work_alert(li,get_val)
                mail_alert(li,get_val)
            elif li[20]=="work":
                work_alert(li,get_val)
            elif li[20]=="email":
                mail_alert(li,get_val)
                #return k['planned_downtime']
                # return get_val
        elif(li[22]==current_Time_compare):
            print("metrics  string matching")
            print(current_Time_compare)
            print(li[22])
            update_matched_time(li[0],future_date_Time)
            if li[20]=="all":
                work_alert(li,get_val)
                mail_alert(li,get_val)
            elif li[20]=="work":
                work_alert(li,get_val)
            elif li[20]=="email":
                mail_alert(li,get_val)
                #return k['planned_downtime']
                # return get_val
        else:
            if current_Time_compare > li[22]:
                update_matched_time(li[0],one_hour_extend)
            print("empty records")
            print(current_Time_compare)
            print(li[22])
            # return "empty"
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
    
    print(previous_date_Time)
    print(current_date_time)
    par = {
        "from_time": previous_date_Time,
        "to_time": current_date_time,
        "machine_arr":i[6],
        "part_arr":i[7],
        "site_id":"s1001",
        "res":i[2],
    }
    api_array = requests.post("http://localhost/smartories/index.php",params=par)
    #final_api_record = json.dumps(api_array)
    print(api_array.status_code)
    if api_array.status_code == 200:
        rp = api_array.json()
        print(rp)
        # print(len(rp))
        print(i)
        # final_res = check_metrics(rp,i)
        # print(final_res)
        # if(final_res !="empty"):
        #     if(i[20]=='all'):
        #         mail_alert(i,final_res)
        #         work_alert(i,final_res)
        #     elif(i[20]=='email'):
        #         mail_alert(i,final_res)
        #     elif(i[20]=='work'):
        #         work_alert(i,final_res)  
        check_metrics(rp,i)          
        # else:
        #     print("Error")
            
        print(type(api_array))
        print(i[2])

    # elif(i[2]=="unplanned_downtime"):
    #     print("planned downtime")
    # elif(i[2]=="planned_machine_off"):
    #     print("planned machine off")
    # elif(i[2]=="unplanned_machine_off"):
    #     print("unplanned machine off")

def mysql_res():
    mydb = mysql.connector.connect(
        host="localhost",
        username="root",
        password="",
        database="s1001"
    )

    mycursor = mydb.cursor()
    mycursor.execute("SELECT * FROM alert_settings WHERE alert_status!=1")
    myres = mycursor.fetchall()

    for i in myres:
        print('')
        analysis_metrics(i)

k = 0
while True:
    k = k+1
    mysql_res()
    print("end count of ",k)
    time.sleep(60)