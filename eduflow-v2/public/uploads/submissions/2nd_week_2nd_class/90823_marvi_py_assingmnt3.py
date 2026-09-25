a = True
b = False
print(a and b)
print(a or b)
print(not a)

fruits = {"apple", "banana", "mango"}
print("grapes" in fruits)

x = 10
y = 20
print(x > 5)
print(x < 5)
print(x > 5 and y < 30)

mark = int(input("Enter marks (0-100): "))
if marks > 90:
    print("Grade A")
    if marks > 95:
        print("Outstanding!")
    elif marks > 80:
        print("Grade B")
    elif marks > 60:
        print("Grade C")
    else:
        print("Fail")