print("Enter your full details for admission")

print("This is our First program")
print("Thank you for choosing this python course")
print("Instructor Ashfaque Ali")

print("Instructor Ali\nbelongs to gambat city\ncurrent address \nhyderabad\njob software developer")
students=[]
firstname=input("Enter your first name: ")
students.append(firstname)
lastname=input("Enter your last name: ")
students.append(lastname)
clas=input("Enter your class name: ")
students.append(clas)
age=int(input("Enter your age: "))
students.append(age)
lastdegreepercentage=float(input("Enter your last degree percentage: "))
students.append(lastdegreepercentage)
print("Thank you for choosing this program\n your filled form details")
print("Full Name ",firstname+lastname," \nClass ",clas," \nAge ",age,"\n Perctange: ",lastdegreepercentage)

print(students)

remov=int(input("Enter a number which one you want to remove: "))
students.pop(remov)
print(students)
