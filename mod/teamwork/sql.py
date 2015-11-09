#coding=utf-8
import MySQLdb
import sys

def countActivitys(cursor,teamid,timestart,timeend):
	sql = "select userid from mdl_teamwork_teammembers where team = '" + teamid + "'"
	cursor.execute(sql);
	results = cursor.fetchall()
	sql = "select count(*) from `mdl_logstore_standard_log` where timecreated >= '" + timestart + "' and timecreated <= '" + timeend + "' and ("
	line = "";
	for userid in results:
		if(len(line) == 0):
			line = "userid = '" + str(userid[0]) +"' "
		else:
			line = line + " or userid = '"+str(userid[0]) + "'"
	line = line + ")"
	sql = sql + line
	cursor.execute(sql);
	count = cursor.fetchone()
	print count[0]


if __name__ == "__main__":
	con = MySQLdb.connect(host="localhost",user='root',passwd='',db = 'moodle',charset='utf8')
	cursor = con.cursor()
	countActivitys(cursor,sys.argv[1],sys.argv[2],sys.argv[3])